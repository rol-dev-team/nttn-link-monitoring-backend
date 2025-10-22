<?php

namespace App\Http\Controllers;

use App\Models\CapacityAlertConfig;
use Illuminate\Http\Request;

class CapacityAlertConfigController extends Controller
{
    // GET all capacity alert configs
    public function index()
    {
        $data = CapacityAlertConfig::with('activationPlan')->get();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // GET single capacity alert config
    public function show($id)
    {
        $data = CapacityAlertConfig::with('activationPlan')->find($id);

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

    // POST - create new capacity alert config
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'max_threshold_mbps' => 'required|numeric',
            'max_frequency_per_day' => 'required|integer',
            'max_consecutive_days' => 'required|integer',
            'min_threshold_mbps' => 'required|numeric',
            'min_frequency_per_day' => 'required|integer',
            'min_consecutive_days' => 'required|integer',
        ]);

        $data = CapacityAlertConfig::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data->load('activationPlan')
        ], 201);
    }

    // PUT/PATCH - update capacity alert config
    public function update(Request $request, $id)
    {
        $data = CapacityAlertConfig::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'max_threshold_mbps' => 'required|numeric',
            'max_frequency_per_day' => 'required|integer',
            'max_consecutive_days' => 'required|integer',
            'min_threshold_mbps' => 'required|numeric',
            'min_frequency_per_day' => 'required|integer',
            'min_consecutive_days' => 'required|integer',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data->load('activationPlan')
        ], 200);
    }

    // DELETE - remove capacity alert config
    public function destroy($id)
    {
        $data = CapacityAlertConfig::find($id);

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

