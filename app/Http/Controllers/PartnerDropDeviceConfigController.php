<?php

namespace App\Http\Controllers;

use App\Models\PartnerDropDeviceConfig;
use Illuminate\Http\Request;

class PartnerDropDeviceConfigController extends Controller
{
    // List all drop device configs
    public function index()
    {
        $data = PartnerDropDeviceConfig::with('activationPlan')->get();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // Show single drop device config
    public function show($id)
    {
        $data = PartnerDropDeviceConfig::with('activationPlan')->find($id);

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

    // Create new drop device config
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'device_ip' => 'required|string|max:255',
            'usage_vlan' => 'required|string|max:255',
            'connected_port' => 'required|string|max:255',
        ]);

        $data = PartnerDropDeviceConfig::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data->load('activationPlan')
        ], 201);
    }

    // Update drop device config
    public function update(Request $request, $id)
    {
        $data = PartnerDropDeviceConfig::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'device_ip' => 'required|string|max:255',
            'usage_vlan' => 'required|string|max:255',
            'connected_port' => 'required|string|max:255',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data->load('activationPlan')
        ], 200);
    }

    // Delete drop device config
    public function destroy($id)
    {
        $data = PartnerDropDeviceConfig::find($id);

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

