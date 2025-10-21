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
        $tools = Tool::join('tool_types', 'tools.tool_type_id', '=', 'tool_types.id')
            ->where('tools.object_id', $objectId)
            ->select(
                'tools.*',
                'tool_types.name as tool_type_name',
                'tool_types.icon as tool_type_icon',
                'tool_types.units_per_hour',
                'tool_types.produces_tool_type_id'
            )
            ->get();
        return response()->json($tools);
    }

    public function updateToolPosition(Request $request)
    {
        $request->validate([
            'tool_id' => 'required|exists:tools,id',
            'x' => 'required|integer|min:0|max:9',
            'y' => 'required|integer|min:0|max:9',
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
}