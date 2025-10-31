<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        $userId = Session::get('user_id') ?: \Illuminate\Support\Facades\Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $items = DB::table('inventories')
            ->leftJoin('tool_types', 'inventories.tool_type_id', '=', 'tool_types.id')
            ->where('inventories.user_id', $userId)
            ->select(
                'inventories.id',
                'inventories.tool_type_id',
                'inventories.count',
                'inventories.reserved_count',
                'inventories.temp_count',
                'tool_types.name as tool_name',
                'tool_types.icon as tool_icon',
                'tool_types.description as tool_description'
            )
            ->orderBy('tool_types.name')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }
}
