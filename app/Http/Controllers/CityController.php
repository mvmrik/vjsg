<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityObject;
use Illuminate\Support\Facades\Session;

class CityController extends Controller
{
    public function index()
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $objects = CityObject::where('user_id', $userId)->get();

        return response()->json([
            'success' => true,
            'objects' => $objects
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
            'objects.*.y' => 'required|integer|min:0|max:9',
            'objects.*.width' => 'required|integer|min:1',
            'objects.*.height' => 'required|integer|min:1'
        ]);

        // Get the parcel_id from the first object (all objects should be for the same parcel)
        $parcelId = $request->objects[0]['parcel_id'] ?? null;
        if (!$parcelId) {
            return response()->json(['success' => false, 'message' => 'Invalid parcel_id'], 400);
        }

        // Delete existing objects only for this parcel
        CityObject::where('user_id', $userId)
                  ->where('parcel_id', $parcelId)
                  ->delete();

        // Save new objects
        $objects = [];
        foreach ($request->objects as $objData) {
            // Double-check that all objects are for the same parcel
            if ($objData['parcel_id'] !== $parcelId) {
                continue; // Skip objects for different parcels
            }

            $objects[] = CityObject::create([
                'user_id' => $userId,
                'parcel_id' => $objData['parcel_id'],
                'object_type' => $objData['object_type'],
                'x' => $objData['x'],
                'y' => $objData['y'],
                'width' => $objData['width'],
                'height' => $objData['height'],
                'properties' => $objData['properties'] ?? []
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'City saved successfully',
            'objects' => $objects
        ]);
    }
}
