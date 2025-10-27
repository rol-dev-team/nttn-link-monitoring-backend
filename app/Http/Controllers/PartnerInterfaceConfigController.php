<?php

namespace App\Http\Controllers;

use App\Models\PartnerInterfaceConfig;
use App\Models\PartnerActivationPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

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
//    public function store(Request $request)
//    {
//        $validated = $request->validate([
//            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
//            'interface_name' => 'required|string|max:255',
//            'interface_port' => 'required|string|max:255',
//        ]);
//
//        $data = PartnerInterfaceConfig::create($validated);
//
//        return response()->json([
//            'status' => true,
//            'message' => 'Created successfully.',
//            'data' => $data->load('activationPlan')
//        ], 201);
//    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'activation_plan_id' => 'required|exists:partner_activation_plans,id',
            'interface_name' => 'required|string|max:255',
//            'interface_port' => 'required|string|max:255',
        ]);

        try {

            $activationPlan = PartnerActivationPlan::find($validated['activation_plan_id']);
            $nasIp = $activationPlan->nas_ip;


            $portId = $this->getPortIdFromLibreDB($nasIp, $validated['interface_name']);

            if (!$portId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Port not found in libre_db for the given interface name and NAS IP.',
                ], 404);
            }


            $data = PartnerInterfaceConfig::create([
                'activation_plan_id' => $validated['activation_plan_id'],
                'interface_name' => $validated['interface_name'],
                'interface_port' => $portId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Created successfully.',
                'data' => $data->load('activationPlan')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getPortIdFromLibreDB($nasIp, $interfaceName)
    {


        $result = DB::connection('librenms')
            ->table('devices as d')
            ->join('ports as p', 'd.device_id', '=', 'p.device_id')
            ->where('d.hostname', $nasIp)
            ->where('p.ifName', $interfaceName)
            ->select('p.port_id')
            ->first();

        return $result ? $result->port_id : null;
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
            'interface_port' => 'required|string|max:255',
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



    public function fetchNasIpLocal(): JsonResponse
    {
        try {

            $nasIps = PartnerActivationPlan::where('status', 'active')
                ->whereNotNull('nas_ip')
                ->select('id', 'nas_ip')
                ->get()
                ->toArray();

            if (empty($nasIps)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active NAS IPs found.',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Active NAS IPs fetched successfully.',
                'data' => $nasIps
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching NAS IPs: ' . $e->getMessage(),
            ], 500);
        }
    }
}

