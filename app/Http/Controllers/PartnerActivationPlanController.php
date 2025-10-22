<?php

namespace App\Http\Controllers;

use App\Models\PartnerActivationPlan;
use Illuminate\Http\Request;

class PartnerActivationPlanController extends Controller
{
    // GET all activation plans
    public function index()
    {
        $data = PartnerActivationPlan::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    // GET single plan
    public function show($id)
    {
        $data = PartnerActivationPlan::find($id);

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

    // POST - create new plan
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_order_id' => 'nullable|string|max:255',
            'int_routing_ip' => 'nullable|string|max:255',
            'ggc_routing_ip' => 'nullable|string|max:255',
            'fna_routing_ip' => 'nullable|string|max:255',
            'bcdx_routing_ip' => 'nullable|string|max:255',
            'mcdn_routing_ip' => 'nullable|string|max:255',
            'nttn_vlan' => 'nullable|string|max:255',
            'int_vlan' => 'nullable|string|max:255',
            'ggn_vlan' => 'nullable|string|max:255',
            'fna_vlan' => 'nullable|string|max:255',
            'bcdx_vlan' => 'nullable|string|max:255',
            'mcdn_vlan' => 'nullable|string|max:255',
            'nas_ip' => 'required|string|max:255|unique:partner_activation_plans,nas_ip',
            'nat_ip' => 'required|string|max:255|unique:partner_activation_plans,nat_ip',
            'connected_ws_name' => 'nullable|string|max:255',
            'chr_server' => 'nullable|string|max:255',
            'sw_port' => 'nullable|integer',
            'nic_no' => 'nullable|string|max:255',
            'asn' => 'nullable|integer',
            'status' => 'in:active,inactive',
            'note' => 'nullable|string',
        ]);

        $data = PartnerActivationPlan::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully.',
            'data' => $data
        ], 201);
    }

    // PUT/PATCH - update existing plan
    public function update(Request $request, $id)
    {
        $data = PartnerActivationPlan::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.'
            ], 404);
        }

        $validated = $request->validate([
            'work_order_id' => 'nullable|string|max:255',
            'int_routing_ip' => 'nullable|string|max:255',
            'ggc_routing_ip' => 'nullable|string|max:255',
            'fna_routing_ip' => 'nullable|string|max:255',
            'bcdx_routing_ip' => 'nullable|string|max:255',
            'mcdn_routing_ip' => 'nullable|string|max:255',
            'nttn_vlan' => 'nullable|string|max:255',
            'int_vlan' => 'nullable|string|max:255',
            'ggn_vlan' => 'nullable|string|max:255',
            'fna_vlan' => 'nullable|string|max:255',
            'bcdx_vlan' => 'nullable|string|max:255',
            'mcdn_vlan' => 'nullable|string|max:255',
            'nas_ip' => 'required|string|max:255|unique:partner_activation_plans,nas_ip,' . $id,
            'nat_ip' => 'required|string|max:255|unique:partner_activation_plans,nat_ip,' . $id,
            'connected_ws_name' => 'nullable|string|max:255',
            'chr_server' => 'nullable|string|max:255',
            'sw_port' => 'nullable|integer',
            'nic_no' => 'nullable|string|max:255',
            'asn' => 'nullable|integer',
            'status' => 'in:active,inactive',
            'note' => 'nullable|string',
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
        $data = PartnerActivationPlan::find($id);

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

