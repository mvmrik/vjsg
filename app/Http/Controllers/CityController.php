<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\ObjectType;
use App\Models\Person;
use App\Models\OccupiedWorker;
use App\Models\Parcel;
use App\Services\MarketService;
use Illuminate\Support\Facades\Log;

class CityController extends Controller
{
    public function index()
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        // NOTE: finishing (transfer temp->count and freeing workers) is handled by the scheduled
        // command `check:completed-builds`. We no longer clear ready_at or free workers on page
        // load to avoid race conditions and to centralize finalization logic in the cron job.
        $cleaned = 0;

        $objects = CityObject::where('user_id', $userId)->get();

        // Annotate build_seconds for frontend convenience
        $types = ObjectType::all()->keyBy('type');
        
        // Get occupied workers for objects that are building (active) and all occupied records
        $occupiedWorkers = OccupiedWorker::where('user_id', $userId)
            ->where('occupied_until', '>', time())
            ->get()
            ->keyBy('city_object_id');

        // Also fetch any occupied_worker records for this user (regardless of occupied_until)
        // so we can detect expired-but-not-yet-cleaned entries and avoid showing action buttons
        $allOccupied = OccupiedWorker::where('user_id', $userId)
            ->get()
            ->keyBy('city_object_id');
        
        $objsArr = $objects->map(function ($o) use ($types, $occupiedWorkers) {
            $arr = $o->toArray();

            // Ensure level is always present and numeric for frontend convenience
            if (!array_key_exists('level', $arr) || $arr['level'] === null) {
                $arr['level'] = 1;
            } else {
                $arr['level'] = intval($arr['level']);
            }
            
            // ready_at is already an integer timestamp, no conversion needed
            // Frontend expects milliseconds, so multiply by 1000
            if ($o->ready_at) {
                $arr['ready_at'] = $o->ready_at * 1000; // Convert to milliseconds
            }
            
            // If build_seconds is missing, calculate it from object type
            if (!isset($arr['build_seconds']) || $arr['build_seconds'] === null) {
                $type = $types[$o->object_type] ?? null;
                if ($type) {
                    $arr['build_seconds'] = intval($type->build_time_minutes) * 60;
                } else {
                    $arr['build_seconds'] = 60;
                }
            }
            
            // Add occupied workers info if building (active workers)
            if (isset($occupiedWorkers[$o->id])) {
                $worker = $occupiedWorkers[$o->id];
                $arr['workers'] = [
                    'level' => $worker->level,
                    'count' => $worker->count
                ];
            }

            // finalized: true when object is not building (no ready_at) AND there are no occupied_worker
            // records at all for this object (this prevents the frontend from showing action buttons
            // when occupied_worker rows exist but their occupied_until has already passed and cron
            // hasn't cleaned them yet).
            $arr['finalized'] = (!$o->ready_at) && !isset($allOccupied[$o->id]);

            return $arr;
        });

        return response()->json([
            'success' => true,
            'cleaned' => $cleaned,
            'objects' => $objsArr
        ]);
    }

    public function save(Request $request)
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $request->validate([
            'objects' => 'required|array',
            'objects.*.parcel_id' => 'required|integer',
            'objects.*.object_type' => 'required|string',
            'objects.*.x' => 'required|integer|min:0|max:4',
            'objects.*.y' => 'required|integer|min:0|max:4'
        ]);

        // Get the parcel_id from the first object (all objects should be for the same parcel)
        $parcelId = $request->objects[0]['parcel_id'] ?? null;
        if (!$parcelId) {
            return response()->json(['success' => false, 'message' => 'Invalid parcel_id'], 400);
        }

        // VALIDATION: Verify parcel belongs to current user
        $parcel = Parcel::where('id', $parcelId)->where('user_id', $userId)->first();
        if (!$parcel) {
            return response()->json([
                'success' => false, 
                'message' => 'Access denied: Parcel does not belong to you'
            ], 403);
        }

        // Determine which incoming objects have IDs (existing) vs new
        $objects = [];
        $incomingIds = [];
        foreach ($request->objects as $objData) {
            if (($objData['parcel_id'] ?? null) !== $parcelId) continue;
            if (!empty($objData['id'])) {
                $incomingIds[] = $objData['id'];
            }
        }

        // Delete existing objects for this parcel that are not present in incoming payload.
        // Safety: if the client did not include any existing IDs in the payload (incomingIds empty)
        // we assume the client did not intend to remove existing objects and skip deletion to
        // avoid accidental data loss.
        if (!empty($incomingIds)) {
            $toDeleteQuery = CityObject::where('user_id', $userId)
                ->where('parcel_id', $parcelId)
                ->whereNotIn('id', $incomingIds);
            $toDeleteQuery->delete();
        }

        // Process incoming objects: update existing ones, create new ones
        foreach ($request->objects as $objData) {
            if (($objData['parcel_id'] ?? null) !== $parcelId) continue;

            if (!empty($objData['id'])) {
                // Update existing object (but do not reset ready_at)
                $existing = CityObject::where('user_id', $userId)->where('id', $objData['id'])->first();
                if ($existing) {
                    $existing->object_type = $objData['object_type'];
                    $existing->x = $objData['x'];
                    $existing->y = $objData['y'];
                    // properties removed - no longer needed
                    $existing->save();
                    $objects[] = $existing;
                }
            } else {
                // New object: compute ready_at based on object type table (minutes)
                $type = ObjectType::where('type', $objData['object_type'])->first();
                
                // VALIDATION: Ensure object type exists in database
                if (!$type) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Invalid object type: ' . $objData['object_type']
                    ], 400);
                }
                
                $baseSeconds = intval($type->build_time_minutes) * 60;

                // VALIDATION: Ensure cells are within grid bounds and not overlapping with existing objects
                $x = $objData['x'];
                $y = $objData['y'];
                if ($x < 0 || $x > 4 || $y < 0 || $y > 4) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Invalid cell coordinates: x and y must be between 0-9'
                    ], 400);
                }

                // Check for overlap with existing objects on this parcel
                $existing = CityObject::where('parcel_id', $parcelId)
                    ->where('x', $x)
                    ->where('y', $y)
                    ->first();
                if ($existing) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Cell already occupied by another object'
                    ], 400);
                }

                // Check for workers info sent in properties (level & count)
                $workers = ($objData['properties'] ?? [])['workers'] ?? null;
                // Determine current object level (if provided in payload for upgrades), default to 0
                // We calculate time for the NEXT level (current + 1) inside the helper
                $objectLevel = intval($objData['level'] ?? 0);
                
                // VALIDATION: Verify user actually has the claimed workers
                if ($workers && isset($workers['level']) && isset($workers['count'])) {
                    $level = intval($workers['level']);
                    $count = intval($workers['count']);
                    
                    // Query people table to verify user has this many workers at this level
                    $personGroup = Person::where('user_id', $userId)
                        ->where('level', $level)
                        ->first();
                    
                    if (!$personGroup || $personGroup->count < $count) {
                        return response()->json([
                            'success' => false, 
                            'message' => 'Invalid workers: You do not have ' . $count . ' workers at level ' . $level
                        ], 400);
                    }
                    
                    // Use centralized helper that applies the 'next level' logic
                    $buildSeconds = \App\Models\CityObject::calculateBuildSeconds($baseSeconds, $objectLevel, $level, $count);
                } else {
                    $buildSeconds = \App\Models\CityObject::calculateBuildSeconds($baseSeconds, $objectLevel, 0, 0);
                }

                $readyAt = time() + $buildSeconds; // UNIX timestamp

                // Prepare creation payload. Only set 'level' when it was provided by client
                // so the database default (if any) can apply when client doesn't send level.
                $createData = [
                    'user_id' => $userId,
                    'parcel_id' => $objData['parcel_id'],
                    'object_type' => $objData['object_type'],
                    'x' => $objData['x'],
                    'y' => $objData['y'],
                    'ready_at' => $readyAt,
                    'build_seconds' => $buildSeconds
                ];
                if (array_key_exists('level', $objData)) {
                    $createData['level'] = $objectLevel;
                } else {
                    // Defensive: explicitly set to 1 if client didn't provide level so hosts
                    // with different DB defaults behave consistently with local dev.
                    $createData['level'] = 1;
                }

                // If object type has a recipe (required materials), verify and consume them
                $recipe = $type->recipe ?? null; // expected array: [tool_type_id => qty]
                if ($recipe && is_array($recipe) && count($recipe) > 0) {
                    // For a NEW building we use multiplier = 1 (user expectation)
                    $multiplier = 1;
                    // Start transaction to lock inventory rows while deducting
                    DB::beginTransaction();
                    try {
                        foreach ($recipe as $toolTypeId => $qty) {
                            $need = intval($qty) * intval($multiplier);
                            if ($need <= 0) continue;
                            $inv = \App\Models\Inventory::where('user_id', $userId)
                                ->where('tool_type_id', intval($toolTypeId))
                                ->lockForUpdate()
                                ->first();

                            $available = $inv ? intval($inv->count) : 0;
                            if ($available < $need) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Insufficient materials: need ' . $need . ' of tool_type ' . $toolTypeId
                                ], 400);
                            }

                            // Deduct
                            $inv->count = $inv->count - $need;
                            $inv->save();
                        }
                        // Create object after successful deduction
                        $created = CityObject::create($createData);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => 'Failed to allocate materials: ' . $e->getMessage()], 500);
                    }
                } else {
                    $created = CityObject::create($createData);
                }

                $arr = $created->toArray();
                $arr['ready_at'] = $readyAt * 1000; // Convert to milliseconds for frontend
                $arr['build_seconds'] = $buildSeconds;
                $objects[] = (object)$arr;

                // If this is a bank, recompute user's market fee
                // Recompute cached aggregate for the created object's type and update related systems
                try {
                    \App\Services\ObjectLevelService::recomputeAndStore($userId, $createData['object_type']);
                    if (($createData['object_type'] ?? null) === 'bank') {
                        MarketService::recomputeUserFee($userId);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to recompute aggregate after object create for user ' . $userId . ': ' . $e->getMessage());
                }

                // OCCUPY WORKERS: Create occupied_worker record if workers were used
                if ($workers && isset($workers['level']) && isset($workers['count'])) {
                    // Transactionally decrement free people and create occupied record
                    $db = DB::connection();
                    $db->beginTransaction();
                    try {
                        $level = intval($workers['level']);
                        $count = intval($workers['count']);

                        // Lock person row for update
                        $personRow = DB::table('people')
                            ->where('user_id', $userId)
                            ->where('level', $level)
                            ->lockForUpdate()
                            ->first();

                        $available = $personRow ? intval($personRow->count) : 0;
                        if ($available < $count) {
                            $db->rollBack();
                            return response()->json(['success' => false, 'message' => 'Insufficient free workers at level ' . $level], 400);
                        }

                        $newCount = $available - $count;
                        if ($newCount <= 0) {
                            DB::table('people')
                                ->where('user_id', $userId)
                                ->where('level', $level)
                                ->delete();
                        } else {
                            DB::table('people')
                                ->where('user_id', $userId)
                                ->where('level', $level)
                                ->update(['count' => $newCount]);
                        }

                        OccupiedWorker::create([
                            'user_id' => $userId,
                            'level' => $level,
                            'count' => $count,
                            'occupied_until' => $readyAt,
                            'city_object_id' => $created->id
                        ]);

                        $db->commit();
                    } catch (\Exception $e) {
                        $db->rollBack();
                        return response()->json(['success' => false, 'message' => 'Failed to occupy workers: ' . $e->getMessage()], 500);
                    }
                }
            }
        }

        // We don't clear ready_at here; finalization (including transferring temp_count -> count
        // and freeing occupied workers) is performed by the scheduled `check:completed-builds`.
        $cleanedAfter = 0;

        $allObjects = CityObject::where('user_id', $userId)->get();
        $types = ObjectType::all()->keyBy('type');
        $allArr = $allObjects->map(function ($o) use ($types) {
            $arr = $o->toArray();

            // Ensure level is present and numeric when returning after save
            if (!array_key_exists('level', $arr) || $arr['level'] === null) {
                $arr['level'] = 1;
            } else {
                $arr['level'] = intval($arr['level']);
            }
            
            // Convert ready_at timestamp to milliseconds for frontend
            if ($o->ready_at) {
                $arr['ready_at'] = $o->ready_at * 1000;
            }
            
            // If build_seconds is missing, calculate it from object type
            if (!isset($arr['build_seconds']) || $arr['build_seconds'] === null) {
                $type = $types[$o->object_type] ?? null;
                if ($type) {
                    $arr['build_seconds'] = intval($type->build_time_minutes) * 60;
                } else {
                    $arr['build_seconds'] = 60;
                }
            }
            
            return $arr;
        });

        return response()->json([
            'success' => true,
            'message' => 'City saved successfully',
            'cleaned_after_save' => $cleanedAfter,
            'objects' => $allArr
        ]);
    }

    /**
     * Return available object types from DB
     */
    public function types()
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $types = ObjectType::orderBy('type')->get();
        return response()->json(['success' => true, 'types' => $types]);
    }

    /**
     * Upgrade object level
     */
    public function upgrade(Request $request)
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $objectId = $request->input('object_id');
        $workerLevel = $request->input('worker_level');
        $workerCount = $request->input('worker_count');

        if (!$objectId || $workerLevel === null || $workerLevel < 0 || $workerCount === null || $workerCount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters: Please select workers'], 400);
        }

        $object = CityObject::where('id', $objectId)->where('user_id', $userId)->first();
        if (!$object) {
            return response()->json(['success' => false, 'message' => 'Object not found'], 404);
        }

        // Check if object is already being upgraded/built
        if ($object->ready_at && $object->ready_at > time()) {
            return response()->json(['success' => false, 'message' => 'Object is already being upgraded'], 400);
        }

        // VALIDATION: Verify user actually has the claimed workers
        if ($workerLevel > 0 && $workerCount > 0) {
            $personGroup = Person::where('user_id', $userId)
                ->where('level', intval($workerLevel))
                ->first();
            
            if (!$personGroup || $personGroup->count < intval($workerCount)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid workers: You do not have ' . $workerCount . ' workers at level ' . $workerLevel
                ], 400);
            }
            // free workers are represented by `people` counts; we'll decrement when occupying
        }

    // Calculate upgrade time using centralized helper (next level logic)
    $objectType = ObjectType::where('type', $object->object_type)->first();
    $baseMinutes = $objectType ? $objectType->build_time_minutes : 10;
    $baseSeconds = $baseMinutes * 60;
    $finalSeconds = \App\Models\CityObject::calculateBuildSeconds($baseSeconds, $object->level ?? 0, intval($workerLevel), intval($workerCount));
    $finalMinutes = intval(ceil($finalSeconds / 60));

    // Handle required recipe materials for upgrade (if any)
    $recipe = $objectType->recipe ?? null;
    $multiplier = intval(($object->level ?? 0)) + 1; // next level multiplier

    // Perform inventory checks and object update in a transaction
    $db = DB::connection();
    $db->beginTransaction();
    try {
        if ($recipe && is_array($recipe) && count($recipe) > 0) {
            foreach ($recipe as $toolTypeId => $qty) {
                $need = intval($qty) * $multiplier;
                if ($need <= 0) continue;
                $inv = \App\Models\Inventory::where('user_id', $userId)
                    ->where('tool_type_id', intval($toolTypeId))
                    ->lockForUpdate()
                    ->first();

                $available = $inv ? intval($inv->count) : 0;
                if ($available < $need) {
                    $db->rollBack();
                    return response()->json(['success' => false, 'message' => 'Insufficient materials: need ' . $need . ' of tool_type ' . $toolTypeId], 400);
                }

                // Deduct
                $inv->count = $inv->count - $need;
                $inv->save();
            }
        }

        // Update object to increase level and set build time
        $readyAt = time() + $finalSeconds; // UNIX timestamp
        $object->level = ($object->level ?? 0) + 1;
        $object->build_seconds = $finalSeconds;
        $object->ready_at = $readyAt;
        $object->save();

        // Recompute cached aggregate for this object's type and update related systems
        try {
            \App\Services\ObjectLevelService::recomputeAndStore($userId, $object->object_type);
            if ($object->object_type === 'bank') {
                \App\Services\MarketService::recomputeUserFee($userId);
            }
        } catch (\Exception $e) {
            Log::error('Failed to recompute aggregate after upgrade for user ' . $userId . ': ' . $e->getMessage());
        }

        // Create occupied workers record and decrement free people atomically
        try {
            $level = intval($workerLevel);
            $count = intval($workerCount);

            // Lock person row and decrement
            $personRow = DB::table('people')
                ->where('user_id', $userId)
                ->where('level', $level)
                ->lockForUpdate()
                ->first();

            $available = $personRow ? intval($personRow->count) : 0;
            if ($available < $count) {
                $db->rollBack();
                return response()->json(['success' => false, 'message' => 'Insufficient free workers at level ' . $level], 400);
            }

            $newCount = $available - $count;
            if ($newCount <= 0) {
                DB::table('people')
                    ->where('user_id', $userId)
                    ->where('level', $level)
                    ->delete();
            } else {
                DB::table('people')
                    ->where('user_id', $userId)
                    ->where('level', $level)
                    ->update(['count' => $newCount]);
            }

            OccupiedWorker::create([
                'user_id' => $userId,
                'level' => $level,
                'count' => $count,
                'occupied_until' => $readyAt,
                'city_object_id' => $object->id
            ]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to occupy workers: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Upgrade started successfully',
            'object' => [
                'id' => $object->id,
                'level' => $object->level,
                'ready_at' => $object->ready_at * 1000, // Convert to milliseconds
                'build_seconds' => $finalSeconds,
                'object_type' => $object->object_type,
                'x' => $object->x,
                'y' => $object->y,
                'parcel_id' => $object->parcel_id,
                'user_id' => $object->user_id
            ],
            'upgrade_time_minutes' => $finalMinutes
        ]);
    } catch (\Exception $e) {
        $db->rollBack();
        return response()->json(['success' => false, 'message' => 'Failed to start upgrade: ' . $e->getMessage()], 500);
    }
    }

    /**
     * Start production on an object (similar to upgrade/build)
     */
    public function produce(Request $request)
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $objectId = $request->input('object_id');
        $workerLevel = $request->input('worker_level');
        $workerCount = $request->input('worker_count');

        if (!$objectId || $workerLevel === null || $workerLevel < 0 || $workerCount === null || $workerCount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid parameters: Please select workers'], 400);
        }

        $object = CityObject::where('id', $objectId)->where('user_id', $userId)->first();
        if (!$object) {
            return response()->json(['success' => false, 'message' => 'Object not found'], 404);
        }

        // Check if object is already running production/build
        if ($object->ready_at && $object->ready_at > time()) {
            return response()->json(['success' => false, 'message' => 'Object is already busy'], 400);
        }

        // Validate workers
        if ($workerLevel > 0 && $workerCount > 0) {
            $personGroup = Person::where('user_id', $userId)
                ->where('level', intval($workerLevel))
                ->first();

            if (!$personGroup || $personGroup->count < intval($workerCount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid workers: You do not have ' . $workerCount . ' workers at level ' . $workerLevel
                ], 400);
            }
            // free workers are represented by `people` counts; we'll decrement when occupying
        }

        // INVERTED: placed tools are products, find their raw
        $toolRows = \App\Models\Tool::where('object_id', $object->id)->get();
        if ($toolRows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No tools attached'], 400);
        }

        // Check if harvest/mine type (no raw materials needed)
        $objectType = \App\Models\ObjectType::where('type', $object->object_type)->first();
        $isHarvest = $objectType && in_array($objectType->type, ['harvest', 'mine']);

        $tools = [];
        foreach ($toolRows as $tr) {
            $productType = \App\Models\ToolType::find($tr->tool_type_id);
            if (!$productType) continue;

            if ($isHarvest) {
                // Harvest: no raw needed
                $tools[] = (object)[
                    'tool_id' => $tr->id,
                    'product_id' => $productType->id,
                    'raw_id' => null,
                    'product_units_per_hour' => $productType->units_per_hour,
                    'product_name' => $productType->name,
                    'raw_name' => null,
                ];
            } else {
                // Factory: need raw
                $rawType = $productType->produces_tool_type_id 
                    ? \App\Models\ToolType::find($productType->produces_tool_type_id)
                    : null;
                if (!$rawType) continue;

                $tools[] = (object)[
                    'tool_id' => $tr->id,
                    'product_id' => $productType->id,
                    'raw_id' => $rawType->id,
                    'product_units_per_hour' => $productType->units_per_hour,
                    'product_name' => $productType->name,
                    'raw_name' => $rawType->name,
                ];
            }
        }

        if (empty($tools)) {
            return response()->json(['success' => false, 'message' => 'No raw materials found'], 400);
        }

    // Production duration: if request provides duration_hours use it; otherwise try user's game_settings; fallback to 12
    $requested = $request->input('duration_hours', null);
    if ($requested !== null) {
        $durationHours = max(1, intval($requested));
    } else {
        $durationHours = 12; // default
        $userId = $request->session()->get('user_id');
        if ($userId) {
            $setting = \App\Models\GameSetting::where('user_id', $userId)->where('key', 'production_length_hours')->first();
            if ($setting && intval($setting->value) > 0) {
                $durationHours = intval($setting->value);
            }
        }
    }
    if ($durationHours > 24) $durationHours = 24; // cap at 24
    $durationSeconds = $durationHours * 3600;

        // Compute production per tool field
        // Formula: perHourPerField = worker_level * worker_count
        // totalPerField = perHourPerField * 24 (hours)
    $perHourMultiplier = intval($workerLevel) * intval($workerCount);
        if ($perHourMultiplier <= 0) $perHourMultiplier = 1; // baseline

        // Group by product
        $groups = [];
        foreach ($tools as $t) {
            $pid = $t->product_id;
            if (!isset($groups[$pid])) {
                $groups[$pid] = [
                    'product_id' => $pid,
                    'raw_id' => $t->raw_id,
                    'fieldsCount' => 0,
                    'product_units_per_hour' => intval($t->product_units_per_hour),
                    'product_name' => $t->product_name,
                    'raw_name' => $t->raw_name,
                ];
            }
            $groups[$pid]['fieldsCount'] += 1;
        }

        $lvl = max(1, intval($workerLevel));
        $cnt = max(1, intval($workerCount));

        // Before writing temp_count, ensure user has enough raw inputs in their inventory.
        // For each produced tool type we will need 1 unit of the raw input PER unit of produced product (baseline).
        // We must check availability in inventories (count) for the raw tool_type_id (which is tool_type_id in groups).
        $db = \Illuminate\Support\Facades\DB::connection();
        $db->beginTransaction();
        try {
            foreach ($groups as $g) {
                $fieldsCount = $g['fieldsCount'];
                $basePerHour = max(0, intval($g['product_units_per_hour']));
                $perHour = $fieldsCount * $basePerHour * $lvl * $cnt;
                $totalProduced = $perHour * $durationHours;

                // Consume raw: 1 per field per hour (SKIP for harvest/mine)
                $rawId = $g['raw_id'];
                if ($rawId) {
                    // Factory: check and consume raw
                    $rawNeeded = intval($fieldsCount) * intval($durationHours);

                    $inventoryRow = \App\Models\Inventory::where('user_id', $userId)
                        ->where('tool_type_id', intval($rawId))
                        ->lockForUpdate()
                        ->first();

                    $availableRaw = $inventoryRow ? intval($inventoryRow->count) : 0;
                    if ($availableRaw < $rawNeeded) {
                        $db->rollBack();
                        return response()->json(['success' => false, 'message' => 'Insufficient ' . $g['raw_name'] . ': need ' . $rawNeeded], 400);
                    }

                    // Deduct raw
                    $inventoryRow->count = $inventoryRow->count - $rawNeeded;
                    $inventoryRow->save();
                }
                // If harvest ($rawId is null), skip raw consumption

                // Add product to per-object production_outputs and increment aggregate inventories.temp_count
                $productId = intval($g['product_id']);
                $toAdd = intval($totalProduced);

                // Create per-object production output record
                \App\Models\ProductionOutput::create([
                    'user_id' => intval($userId),
                    'city_object_id' => $object->id,
                    'tool_type_id' => $productId,
                    'count' => $toAdd
                ]);

                // Also increment aggregate temp_count on inventories to preserve current UI behavior
                $inv = \App\Models\Inventory::where('user_id', $userId)
                    ->where('tool_type_id', $productId)
                    ->lockForUpdate()
                    ->first();
                if ($inv) {
                    $inv->temp_count = intval($inv->temp_count) + $toAdd;
                    $inv->save();
                } else {
                    \App\Models\Inventory::create([
                        'user_id' => intval($userId),
                        'tool_type_id' => $productId,
                        'count' => 0,
                        'temp_count' => $toAdd
                    ]);
                }
            }

            // Set object ready_at and create occupied_workers record
            $readyAt = time() + $durationSeconds;
            $object->ready_at = $readyAt;
            $object->save();

            // Decrement people and create occupied worker (use same transaction)
            $level = intval($workerLevel);
            $count = intval($workerCount);

            $personRow = DB::table('people')
                ->where('user_id', $userId)
                ->where('level', $level)
                ->lockForUpdate()
                ->first();

            $available = $personRow ? intval($personRow->count) : 0;
            if ($available < $count) {
                $db->rollBack();
                return response()->json(['success' => false, 'message' => 'Insufficient free workers at level ' . $level], 400);
            }

            $newCount = $available - $count;
            if ($newCount <= 0) {
                DB::table('people')
                    ->where('user_id', $userId)
                    ->where('level', $level)
                    ->delete();
            } else {
                DB::table('people')
                    ->where('user_id', $userId)
                    ->where('level', $level)
                    ->update(['count' => $newCount]);
            }

            OccupiedWorker::create([
                'user_id' => $userId,
                'level' => $level,
                'count' => $count,
                'occupied_until' => $readyAt,
                'city_object_id' => $object->id
            ]);

            $db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Production started successfully',
                'object' => [
                    'id' => $object->id,
                    'ready_at' => $object->ready_at * 1000,
                    'object_type' => $object->object_type,
                    'x' => $object->x,
                    'y' => $object->y,
                    'parcel_id' => $object->parcel_id,
                    'user_id' => $object->user_id
                ],
                'production_length_hours' => $durationHours
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to start production: ' . $e->getMessage()], 500);
        }
        // end transaction handling
    }
}
