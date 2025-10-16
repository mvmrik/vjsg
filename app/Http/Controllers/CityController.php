<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
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
        $objsArr = $objects->map(function ($o) use ($types) {
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
                    
                    $reductionSeconds = $level * $count * 60;
                    $buildSeconds = max(60, $baseSeconds - $reductionSeconds);
                } else {
                    $buildSeconds = $baseSeconds;
                }

                $readyAt = time() + $buildSeconds; // UNIX timestamp

                $created = CityObject::create([
                    'user_id' => $userId,
                    'parcel_id' => $objData['parcel_id'],
                    'object_type' => $objData['object_type'],
                    'level' => 1,
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

        Log::info('Upgrade request', [
            'object_id' => $objectId,
            'worker_level' => $workerLevel,
            'worker_count' => $workerCount
        ]);

        if (!$objectId || $workerLevel === null || $workerLevel < 0 || $workerCount === null || $workerCount <= 0) {
            Log::error('Invalid parameters', [
                'object_id' => $objectId,
                'worker_level' => $workerLevel,
                'worker_count' => $workerCount,
                'checks' => [
                    'no_object_id' => !$objectId,
                    'level_null' => $workerLevel === null,
                    'level_negative' => $workerLevel < 0,
                    'count_null' => $workerCount === null,
                    'count_zero_or_negative' => $workerCount <= 0
                ]
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid parameters: Please select workers'], 400);
        }

        $object = CityObject::where('id', $objectId)->where('user_id', $userId)->first();
        if (!$object) {
            Log::error('Object not found', ['object_id' => $objectId]);
            return response()->json(['success' => false, 'message' => 'Object not found'], 404);
        }

        // Check if object is already being upgraded/built
        if ($object->ready_at && $object->ready_at > time()) {
            Log::error('Object already upgrading', ['ready_at' => $object->ready_at]);
            return response()->json(['success' => false, 'message' => 'Object is already being upgraded'], 400);
        }

        // VALIDATION: Verify user actually has the claimed workers
        if ($workerLevel > 0 && $workerCount > 0) {
            $personGroup = Person::where('user_id', $userId)
                ->where('level', intval($workerLevel))
                ->first();
            
            Log::info('Worker validation', [
                'personGroup' => $personGroup ? $personGroup->toArray() : null,
                'requested_count' => $workerCount
            ]);
            
            if (!$personGroup || $personGroup->count < intval($workerCount)) {
                Log::error('Not enough workers', [
                    'available' => $personGroup ? $personGroup->count : 0,
                    'requested' => $workerCount
                ]);
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid workers: You do not have ' . $workerCount . ' workers at level ' . $workerLevel
                ], 400);
            }
        }

        // Calculate upgrade time (same as build time of the object type)
        $objectType = ObjectType::where('type', $object->object_type)->first();
        $baseMinutes = $objectType ? $objectType->build_time_minutes : 10;
        $baseSeconds = $baseMinutes * 60;
        $reduction = intval($workerLevel) * intval($workerCount);
        $finalMinutes = max(1, $baseMinutes - $reduction);
        $finalSeconds = max(60, $baseSeconds - ($reduction * 60));

        // Update object to increase level and set build time
        $readyAt = time() + $finalSeconds; // UNIX timestamp
        $object->level = ($object->level ?? 1) + 1;
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
}
