<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;
use App\Models\ObjectType;
use App\Models\Person;
use App\Models\OccupiedWorker;
use App\Models\Parcel;

class CityController extends Controller
{
    public function index()
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        // Clear ready_at for any objects whose ready_at is in the past (they are completed)
        $cleaned = CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', time())
            ->update(['ready_at' => null]);

        // FREE OCCUPIED WORKERS: Delete occupied_worker records for completed buildings
        OccupiedWorker::whereHas('cityObject', function ($query) use ($userId) {
            $query->where('user_id', $userId)->whereNull('ready_at');
        })->delete();

        $objects = CityObject::where('user_id', $userId)->get();

        // Annotate build_seconds for frontend convenience
        $types = ObjectType::all()->keyBy('type');
        
        // Get occupied workers for objects that are building
        $occupiedWorkers = OccupiedWorker::where('user_id', $userId)
            ->where('occupied_until', '>', time())
            ->get()
            ->keyBy('city_object_id');
        
        $objsArr = $objects->map(function ($o) use ($types, $occupiedWorkers) {
            $arr = $o->toArray();
            
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
            
            // Add occupied workers info if building
            if (isset($occupiedWorkers[$o->id])) {
                $worker = $occupiedWorkers[$o->id];
                $arr['workers'] = [
                    'level' => $worker->level,
                    'count' => $worker->count
                ];
            }
            
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
            'objects.*.x' => 'required|integer|min:0|max:9',
            'objects.*.y' => 'required|integer|min:0|max:9'
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

        // Delete existing objects for this parcel that are not present in incoming payload
        $toDeleteQuery = CityObject::where('user_id', $userId)->where('parcel_id', $parcelId);
        if (!empty($incomingIds)) {
            $toDeleteQuery = $toDeleteQuery->whereNotIn('id', $incomingIds);
        }
        $toDeleteQuery->delete();

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
                if ($x < 0 || $x > 9 || $y < 0 || $y > 9) {
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

                $created = CityObject::create([
                    'user_id' => $userId,
                    'parcel_id' => $objData['parcel_id'],
                    'object_type' => $objData['object_type'],
                    'level' => $objectLevel,
                    'x' => $objData['x'],
                    'y' => $objData['y'],
                    'ready_at' => $readyAt,
                    'build_seconds' => $buildSeconds
                ]);
                $arr = $created->toArray();
                $arr['ready_at'] = $readyAt * 1000; // Convert to milliseconds for frontend
                $arr['build_seconds'] = $buildSeconds;
                $objects[] = (object)$arr;

                // OCCUPY WORKERS: Create occupied_worker record if workers were used
                if ($workers && isset($workers['level']) && isset($workers['count'])) {
                    OccupiedWorker::create([
                        'user_id' => $userId,
                        'level' => intval($workers['level']),
                        'count' => intval($workers['count']),
                        'occupied_until' => $readyAt,
                        'city_object_id' => $created->id
                    ]);
                }
            }
        }

        // After creating new objects, clear any expired ready_at values for the user
        CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', time())
            ->update(['ready_at' => null]);

        // Return the full, updated list of objects for the user so frontend stays consistent
        $cleanedAfter = CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', time())
            ->update(['ready_at' => null]);

        $allObjects = CityObject::where('user_id', $userId)->get();
        $types = ObjectType::all()->keyBy('type');
        $allArr = $allObjects->map(function ($o) use ($types) {
            $arr = $o->toArray();
            
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
        }

    // Calculate upgrade time using centralized helper (next level logic)
    $objectType = ObjectType::where('type', $object->object_type)->first();
    $baseMinutes = $objectType ? $objectType->build_time_minutes : 10;
    $baseSeconds = $baseMinutes * 60;
    $finalSeconds = \App\Models\CityObject::calculateBuildSeconds($baseSeconds, $object->level ?? 0, intval($workerLevel), intval($workerCount));
    $finalMinutes = intval(ceil($finalSeconds / 60));

        // Update object to increase level and set build time
    $readyAt = time() + $finalSeconds; // UNIX timestamp
    $object->level = ($object->level ?? 0) + 1;
        $object->build_seconds = $finalSeconds;
        $object->ready_at = $readyAt;
        $object->save();

        // Create occupied workers record
        OccupiedWorker::create([
            'user_id' => $userId,
            'level' => intval($workerLevel),
            'count' => intval($workerCount),
            'occupied_until' => $readyAt,
            'city_object_id' => $object->id
        ]);

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
        }

        // Find tools attached to this object that have production_seconds set
        $tools = \App\Models\Tool::where('object_id', $object->id)
            ->join('tool_types', 'tools.tool_type_id', '=', 'tool_types.id')
            ->select('tools.*', 'tool_types.units_per_hour', 'tool_types.produces_tool_type_id', 'tool_types.name as tool_type_name')
            ->get();

        if ($tools->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No materials/tools attached to this object for production'], 400);
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

        // Prepare inventory updates (temp_count)
        // Group tools by tool_type_id to avoid double-counting
        $groups = [];
        foreach ($tools as $tool) {
            if (!$tool->units_per_hour || !$tool->produces_tool_type_id) continue;
            $tid = $tool->tool_type_id;
            if (!isset($groups[$tid])) {
                $groups[$tid] = [
                    'tool_type_id' => $tid,
                    'fieldsCount' => 0,
                    'units_per_hour' => intval($tool->units_per_hour),
                    'produces_tool_type_id' => $tool->produces_tool_type_id,
                    'tool_type_name' => $tool->tool_type_name ?? null,
                ];
            }
            $groups[$tid]['fieldsCount'] += 1;
        }

        $lvl = max(1, intval($workerLevel));
        $cnt = max(1, intval($workerCount));

        foreach ($groups as $g) {
            $fieldsCount = $g['fieldsCount'];
            $basePerHour = max(0, intval($g['units_per_hour'])); // units per hour per field from DB

            // Per hour production = fieldsCount * basePerHour * workerLevel * workerCount
            $perHour = $fieldsCount * $basePerHour * $lvl * $cnt;
            $totalProduced = $perHour * $durationHours; // for selected hours

            // Atomically insert or increment temp_count to avoid races and ensure accumulation
            // Use INSERT ... ON DUPLICATE KEY UPDATE (works on MySQL). The unique index on (user_id, tool_type_id)
            // guarantees correct behavior.
            $now = date('Y-m-d H:i:s');
            $userIdEsc = intval($userId);
            $toolTypeIdEsc = intval($g['produces_tool_type_id']);
            $toAdd = intval($totalProduced);

            \Illuminate\Support\Facades\DB::statement(
                'INSERT INTO inventories (user_id, tool_type_id, count, temp_count, created_at, updated_at) VALUES (?, ?, 0, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE temp_count = temp_count + ?, updated_at = ?;',
                [$userIdEsc, $toolTypeIdEsc, $toAdd, $now, $now, $toAdd, $now]
            );
        }

        // Set object ready_at and create occupied_workers record
        $readyAt = time() + $durationSeconds;
        $object->ready_at = $readyAt;
        $object->save();

        OccupiedWorker::create([
            'user_id' => $userId,
            'level' => intval($workerLevel),
            'count' => intval($workerCount),
            'occupied_until' => $readyAt,
            'city_object_id' => $object->id
        ]);

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
    }
}
