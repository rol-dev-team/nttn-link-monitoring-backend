<?php

namespace App\Http\Controllers;

use App\Models\TechnicalKam;
use Illuminate\Http\Request;

class TechnicalKamController extends Controller
{
    // GET all records
    public function index()
    {
        $data = TechnicalKam::all();
        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // GET single record by ID
    public function show($id)
    {
        $data = TechnicalKam::find($id);

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

    // POST - create new record
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:20',
            'status' => 'in:active,inactive',
        ]);

        $data = TechnicalKam::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data
        ], 201);
    }

    // PUT/PATCH - update existing record
    public function update(Request $request, $id)
    {
        $data = TechnicalKam::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:20',
            'status' => 'in:active,inactive',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data
        ], 200);
    }

    // DELETE - remove record
    public function destroy($id)
    {
        $data = TechnicalKam::find($id);

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

