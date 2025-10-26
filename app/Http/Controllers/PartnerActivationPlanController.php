<?php

namespace App\Http\Controllers;

use App\Models\PartnerActivationPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

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
//    public function store(Request $request)
//    {
//        $validated = $request->validate([
//            'work_order_id' => 'nullable|string|max:255',
//            'int_routing_ip' => 'nullable|string|max:255',
//            'ggc_routing_ip' => 'nullable|string|max:255',
//            'fna_routing_ip' => 'nullable|string|max:255',
//            'bcdx_routing_ip' => 'nullable|string|max:255',
//            'mcdn_routing_ip' => 'nullable|string|max:255',
//            'nttn_vlan' => 'nullable|string|max:255',
//            'int_vlan' => 'nullable|string|max:255',
//            'ggn_vlan' => 'nullable|string|max:255',
//            'fna_vlan' => 'nullable|string|max:255',
//            'bcdx_vlan' => 'nullable|string|max:255',
//            'mcdn_vlan' => 'nullable|string|max:255',
//            'nas_ip' => 'required|string|max:255|unique:partner_activation_plans,nas_ip',
//            'nat_ip' => 'required|string|max:255|unique:partner_activation_plans,nat_ip',
//            'connected_ws_name' => 'nullable|string|max:255',
//            'chr_server' => 'nullable|string|max:255',
//            'sw_port' => 'nullable|integer',
//            'nic_no' => 'nullable|string|max:255',
//            'asn' => 'nullable|integer',
//            'status' => 'in:active,inactive',
//            'note' => 'nullable|string',
//        ]);
//
//        $data = PartnerActivationPlan::create($validated);
//
//        return response()->json([
//            'status' => true,
//            'message' => 'Created successfully.',
//            'data' => $data
//        ], 201);
//    }

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

        DB::beginTransaction();

        try {

            $data = PartnerActivationPlan::create($validated);


            $apiUrl = 'http://rolnms.race.net.bd/api/v0/devices';
            $apiToken = '80c5b7b2360f3d14800897ed2601ca88';

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiToken,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'hostname' => $validated['nas_ip'],
                'snmpver' => 'v2c',
                'community' => 'EarthComm',
                'port_association_mode' => 'ifIndex',
                'port' => '161',
                'transport' => 'udp',
            ]);




            if (!$response->successful()) {

                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to create device in LibreNMS.',
                    'librenms_response' => $response->json(),
                ], 500);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'PartnerActivationPlan and LibreNMS device created successfully.',
                'data' => [
                    'partner_activation_plan' => $data,
                    'librenms_response' => $response->json(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error occurred while creating record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


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

