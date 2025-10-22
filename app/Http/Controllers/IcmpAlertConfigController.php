<?php

namespace App\Http\Controllers;

use App\Models\IcmpAlertConfig;
use Illuminate\Http\Request;

class IcmpAlertConfigController extends Controller
{
    // GET all ICMP alert configs
    public function index()
    {
        $data = IcmpAlertConfig::with('activationPlan')->get();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // GET single ICMP alert config
    public function show($id)
    {
        $data = IcmpAlertConfig::with('activationPlan')->find($id);

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

    // POST - create new ICMP alert config
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'latency_threshold_ms' => 'required|numeric',
        ]);

        $data = IcmpAlertConfig::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data->load('activationPlan')
        ], 201);
    }

    // PUT/PATCH - update ICMP alert config
    public function update(Request $request, $id)
    {
        $data = IcmpAlertConfig::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'latency_threshold_ms' => 'required|numeric',
        ]);

        $data->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => $data->load('activationPlan')
        ], 200);
    }

    // DELETE - remove ICMP alert config
    public function destroy($id)
    {
        $data = IcmpAlertConfig::find($id);

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

