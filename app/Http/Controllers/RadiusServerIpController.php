<?php

namespace App\Http\Controllers;

use App\Models\RadiusServerIp;
use Illuminate\Http\Request;

class RadiusServerIpController extends Controller
{
    // List all records
    public function index()
    {
//        $data = RadiusServerIp::all();
        $data = RadiusServerIp::where('status', 'active')->get();


        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // Show single record
    public function show($id)
    {
        $data = RadiusServerIp::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // Create new record
    public function store(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'status' => 'in:active,inactive',
        ]);

        $data = RadiusServerIp::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data
        ], 201);
    }

    // Update record
    public function update(Request $request, $id)
    {
        $data = RadiusServerIp::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'status' => 'in:active,inactive',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data
        ], 200);
    }

    // Delete record
    public function destroy($id)
    {
        $data = RadiusServerIp::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully.'
        ], 200);
    }
}

