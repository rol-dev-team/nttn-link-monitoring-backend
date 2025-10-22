<?php

namespace App\Http\Controllers;

use App\Models\PartnerInterfaceConfig;
use Illuminate\Http\Request;

class PartnerInterfaceConfigController extends Controller
{
    // List all interface configs
    public function index()
    {
        $data = PartnerInterfaceConfig::with('activationPlan')->get();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // Show single interface config
    public function show($id)
    {
        $data = PartnerInterfaceConfig::with('activationPlan')->find($id);

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

    // Create new interface config
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'interface_name' => 'required|string|max:255',
        ]);

        $data = PartnerInterfaceConfig::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data->load('activationPlan')
        ], 201);
    }

    // Update interface config
    public function update(Request $request, $id)
    {
        $data = PartnerInterfaceConfig::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'interface_name' => 'required|string|max:255',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data->load('activationPlan')
        ], 200);
    }

    // Delete interface config
    public function destroy($id)
    {
        $data = PartnerInterfaceConfig::find($id);

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

