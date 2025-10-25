<?php

namespace App\Http\Controllers;

use App\Models\PartnerInfo;
use Illuminate\Http\Request;

class PartnerInfoController extends Controller
{
    // Get all partner infos with related models


    public function index()
    {
        $data = PartnerInfo::with(['technicalKam', 'radiusServer'])->get();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // Show single partner info
    public function show($id)
    {
        $data = PartnerInfo::with(['technicalKam', 'radiusServer'])->find($id);

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

    // Create new partner info
    public function store(Request $request)
    {
        $validated = $request->validate([
            'word_order_id' => 'nullable|string|max:255',
            'network_code' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'router_identity' => 'nullable|string|max:255',
            'technical_kam_id' => 'nullable|exists:technical_kams,id',
            'radius_server_id' => 'nullable|exists:radius_server_ips,id', // Fixed typo here
        ]);

        $data = PartnerInfo::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data->load(['technicalKam', 'radiusServer'])
        ], 201);
    }

    // Update existing partner info
    public function update(Request $request, $id)
    {
        $data = PartnerInfo::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'word_order_id' => 'nullable|string|max:255',
            'network_code' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'router_identity' => 'nullable|string|max:255',
            'technical_kam_id' => 'nullable|exists:technical_kams,id',
            'radius_server_id' => 'nullable|exists:radius_server_ips,id', // Fixed here too
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data->load(['technicalKam', 'radiusServer'])
        ], 200);
    }

    // Delete partner info
    public function destroy($id)
    {
        $data = PartnerInfo::find($id);

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
