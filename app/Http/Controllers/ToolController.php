<?php

namespace App\Http\Controllers;

use App\Models\CityObject;
use App\Models\Tool;
use App\Models\ToolType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ToolController extends Controller
{
    public function getAvailableTools($objectId)
    {
        $object = CityObject::findOrFail($objectId);

        // ensure the requester owns the object
        $currentUserId = auth()->id() ?: session('user_id');
        if ($object->user_id !== $currentUserId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // CityObject has 'object_type' which is the type string, not ID.
        // Need to get ObjectType by type.
        $objectType = \App\Models\ObjectType::where('type', $object->object_type)->firstOrFail();
        $tools = $objectType->toolTypes;

        return response()->json($tools);
    }

    public function addTool(Request $request)
    {
        $request->validate([
            'object_id' => 'required|exists:city_objects,id',
            'tool_type_id' => 'required|exists:tool_types,id',
            'position_x' => 'required|integer',
            'position_y' => 'required|integer',
        ]);

        $object = CityObject::findOrFail($request->object_id);
        $objectType = \App\Models\ObjectType::where('type', $object->object_type)->firstOrFail();

        // Check if tool_type is allowed for this object_type
        if (!$objectType->toolTypes()->where('tool_type_id', $request->tool_type_id)->exists()) {
            return response()->json(['error' => 'Tool not allowed for this object type'], 403);
        }

        // Prevent rapid repeated additions: reject if a tool was added to this object very recently
        $recentWindowSeconds = 5; // client-side delay is 5s, enforce same on server
        $recent = Tool::where('object_id', $object->id)
            ->where('created_at', '>=', now()->subSeconds($recentWindowSeconds))
            ->exists();
        if ($recent) {
            return response()->json(['error' => 'Too many tool additions. Please wait a moment and try again.'], 429);
        }

        // Ensure the target position is free
        $posConflict = Tool::where('object_id', $request->object_id)
            ->where('position_x', $request->position_x)
            ->where('position_y', $request->position_y)
            ->exists();
        if ($posConflict) {
            return response()->json(['error' => 'Position already occupied'], 400);
        }

        // Ensure caller owns the object (support legacy session-based auth)
        $currentUserId = auth()->id() ?: $request->session()->get('user_id');
        if ($object->user_id !== $currentUserId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Ensure durability is set to full on creation (DB default is 100, but be explicit)
    $data = $request->only(['object_id', 'tool_type_id', 'position_x', 'position_y']);
    $data['durability'] = 100;

    // Create tool and recompute aggregate inside a transaction so cache update is atomic
    try {
        DB::beginTransaction();

        // Verify inventory: user must have at least 1 available item of this tool_type
        $parent = CityObject::find($data['object_id']);
        if (!$parent) {
            DB::rollBack();
            return response()->json(['error' => 'Parent object not found'], 404);
        }

        $inv = \App\Models\Inventory::where('user_id', $parent->user_id)
            ->where('tool_type_id', $data['tool_type_id'])
            ->lockForUpdate()
            ->first();

        $available = 0;
        if ($inv) {
            $available = intval($inv->count) - intval($inv->reserved_count ?? 0) - intval($inv->temp_count ?? 0);
        }

        if ($available < 1) {
            DB::rollBack();
            return response()->json(['error' => 'Insufficient inventory: you do not have this tool'], 400);
        }

        // consume one unit from inventory
        $newCount = intval($inv->count) - 1;
        if ($newCount <= 0) {
            $inv->delete();
        } else {
            $inv->count = $newCount;
            $inv->save();
        }

        $created = Tool::create($data);

        // Recompute aggregate for parent object type in the same transaction
        $otype = $parent->object_type;
        \App\Services\ObjectLevelService::recomputeAndStore($parent->user_id, $otype);
        // recomputeAndStore already updates MarketService for banks internally

        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to create tool and recompute aggregate: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to add tool'], 500);
    }

    return response()->json(['success' => true, 'tool_id' => $created->id]);
    }

    public function getTools($objectId)
    {
        // INVERTED MODEL: placed tool is product, find raw via produces_tool_type_id
        $toolRows = Tool::where('object_id', $objectId)->get();
        $result = [];

        foreach ($toolRows as $tool) {
            $productType = ToolType::find($tool->tool_type_id);
            if (!$productType) continue;

            // Find raw: product.produces_tool_type_id = raw.id (inverted: product points to raw)
            $rawType = $productType->produces_tool_type_id 
                ? ToolType::find($productType->produces_tool_type_id) 
                : null;

            $result[] = (object)[
                'id' => $tool->id,
                'object_id' => $tool->object_id,
                'tool_type_id' => $tool->tool_type_id,
                'position_x' => $tool->position_x,
                'position_y' => $tool->position_y,
                'tool_type_name' => $productType->name,
                'tool_type_icon' => $productType->icon,
                'units_per_hour' => $productType->units_per_hour, // product units_per_hour
                'produces_tool_type_id' => $productType->produces_tool_type_id,
                'produces_tool_type_name' => $productType->name,
                // INVERTED fields
                'raw_tool_type_id' => $rawType ? $rawType->id : null,
                'raw_name' => $rawType ? $rawType->name : null,
                'product_units_per_hour' => $productType->units_per_hour, // USE THIS for production calculation
                'durability' => isset($tool->durability) ? intval($tool->durability) : (isset($tool->level) ? intval($tool->level) : 100),
            ];
        }

        return response()->json($result);
    }

    public function updateToolPosition(Request $request)
    {
        $request->validate([
            'tool_id' => 'required|exists:tools,id',
            'x' => 'required|integer|min:0|max:4',
            'y' => 'required|integer|min:0|max:4',
        ]);

        $tool = Tool::findOrFail($request->tool_id);

        // Check if position is occupied
        $existing = Tool::where('object_id', $tool->object_id)
            ->where('position_x', $request->x)
            ->where('position_y', $request->y)
            ->where('id', '!=', $tool->id)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Position already occupied'], 400);
        }

        $tool->position_x = $request->x;
        $tool->position_y = $request->y;
        $tool->save();

        return response()->json(['success' => true]);
    }

    public function deleteTool($toolId)
    {
        $tool = Tool::findOrFail($toolId);

        // Only owner of the object can delete tools
        $object = $tool->object;
        $userId = auth()->id() ?: session('user_id');
        if (!$object || $object->user_id !== $userId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $object = $tool->object;
        $userId = $object ? $object->user_id : null;

        try {
            DB::beginTransaction();
            $tool->delete();

            if ($object && $userId) {
                $otype = $object->object_type;
                \App\Services\ObjectLevelService::recomputeAndStore($userId, $otype);
                // recomputeAndStore will update MarketService for banks
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete tool and recompute aggregate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete tool'], 500);
        }

        return response()->json(['success' => true]);
    }

    // Return list of all tool types for market product selection
    public function listToolTypes()
    {
        // Exclude raw building/food materials from market selection — these resources
        // are free/unlimited and shouldn't appear in the market. Filter by id to
        // avoid i18n/name issues (IDs 1 and 2 are raw resources).
        $excludedIds = [1, 2];

        $types = \App\Models\ToolType::whereNotIn('id', $excludedIds)->orderBy('name')->get();
        return response()->json(['success' => true, 'tool_types' => $types]);
    }
}