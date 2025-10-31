<?php

namespace App\Http\Controllers;

use App\Models\CityObject;
use App\Models\Tool;
use App\Models\ToolType;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function getAvailableTools($objectId)
    {
        $object = CityObject::findOrFail($objectId);

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

        Tool::create($request->only(['object_id', 'tool_type_id', 'position_x', 'position_y']));

        return response()->json(['success' => true]);
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