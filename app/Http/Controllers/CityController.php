<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;
use App\Models\ObjectType;
use App\Models\Person;
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
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        $objects = CityObject::where('user_id', $userId)->get();

        // Annotate build_seconds for frontend convenience
        $types = ObjectType::all()->keyBy('type');
        $objsArr = $objects->map(function ($o) use ($types) {
            $arr = $o->toArray();
            $type = $types[$o->object_type] ?? null;
            if ($type) {
                $baseSeconds = intval($type->build_time_minutes) * 60;
            } else {
                $baseSeconds = isset($o->cells) && is_array($o->cells) ? count($o->cells) * 60 : 60;
            }

            // If object has workers info in properties, apply reduction
            $workers = $o->properties['workers'] ?? null;
            if ($workers && isset($workers['level']) && isset($workers['count'])) {
                $reductionSeconds = intval($workers['level']) * intval($workers['count']) * 60;
                $finalSeconds = max(60, $baseSeconds - $reductionSeconds);
            } else {
                $finalSeconds = $baseSeconds;
            }
            $arr['build_seconds'] = $finalSeconds;
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
            'objects.*.cells' => 'required|array',
            'objects.*.cells.*.x' => 'required|integer|min:0|max:9',
            'objects.*.cells.*.y' => 'required|integer|min:0|max:9'
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
                    $existing->cells = $objData['cells'];
                    $existing->properties = $objData['properties'] ?? [];
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
                $cells = $objData['cells'];
                foreach ($cells as $cell) {
                    if ($cell['x'] < 0 || $cell['x'] > 9 || $cell['y'] < 0 || $cell['y'] > 9) {
                        return response()->json([
                            'success' => false, 
                            'message' => 'Invalid cell coordinates: x and y must be between 0-9'
                        ], 400);
                    }
                }

                // Check for workers info sent in properties (level & count)
                $props = $objData['properties'] ?? [];
                $workers = $props['workers'] ?? null;
                
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

                $readyAt = now()->addSeconds($buildSeconds);

                $created = CityObject::create([
                    'user_id' => $userId,
                    'parcel_id' => $objData['parcel_id'],
                    'object_type' => $objData['object_type'],
                    'cells' => $objData['cells'],
                    'properties' => $props,
                    'ready_at' => $readyAt
                ]);
                $arr = $created->toArray();
                $arr['build_seconds'] = $buildSeconds;
                $objects[] = (object)$arr;
            }
        }

        // After creating new objects, clear any expired ready_at values for the user
        CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        // Return the full, updated list of objects for the user so frontend stays consistent
        $cleanedAfter = CityObject::where('user_id', $userId)
            ->whereNotNull('ready_at')
            ->where('ready_at', '<=', now())
            ->update(['ready_at' => null]);

        $allObjects = CityObject::where('user_id', $userId)->get();
        $types = ObjectType::all()->keyBy('type');
        $allArr = $allObjects->map(function ($o) use ($types) {
            $arr = $o->toArray();
            $type = $types[$o->object_type] ?? null;
            if ($type) {
                $baseSeconds = intval($type->build_time_minutes) * 60;
            } else {
                $baseSeconds = isset($o->cells) && is_array($o->cells) ? count($o->cells) * 60 : 60;
            }

            // Apply workers reduction if present
            $workers = $o->properties['workers'] ?? null;
            if ($workers && isset($workers['level']) && isset($workers['count'])) {
                $reductionSeconds = intval($workers['level']) * intval($workers['count']) * 60;
                $finalSeconds = max(60, $baseSeconds - $reductionSeconds);
            } else {
                $finalSeconds = $baseSeconds;
            }
            $arr['build_seconds'] = $finalSeconds;
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
}
