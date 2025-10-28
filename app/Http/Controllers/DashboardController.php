<?php

namespace App\Http\Controllers;

use App\Models\PartnerInfo;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function getSummary()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getSummaryDetails(Request $request)
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }
    public function getPartnerInfos()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getAggregators()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getMaxUtilizationAlert()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getMinUtilizationAlert()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }
    public function getICMPAlert()
    {
        $data = DB::select("SELECT
            pap.*,
            iac.latency_threshold_ms,
            nil.threshold_exceeded_value
        FROM
            public.partner_activation_plans pap
        INNER JOIN
            public.icmp_alert_configs iac
            ON iac.activation_plan_id = pap.id
        INNER JOIN
            public.nas_icmp_latencies nil
            ON nil.activation_plan_id = pap.id
        WHERE
            nil.threshold_exceeded_value::NUMERIC >= iac.latency_threshold_ms::NUMERIC");

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getMinMaxUtilizationLastSevenDays()
    {
        $data = PartnerInfo::all();

        return response()->json([
            'status' => true,
            'message' => 'Retrieved successfully.',
            'data' => $data
        ], 200);
    }

    public function getLastSevenDaysAverageUtilization(): JsonResponse
    {
        $lastSevenDays = DB::connection('pgsql')->select("select DATE(r.collected_at) as days , round(avg(r.utilization_mb), 2) AS avg_utilization
        FROM router_bw_statistics_logs r
        WHERE r.category_type = 'primary'
        AND r.collected_at >= NOW() - INTERVAL '7 days'
        group by DATE(r.collected_at)
        order by days DESC");

        return response()->json([
            'success' => true,
            'data' => $lastSevenDays,
            'message' => 'retrieved successfully'
        ]);
    }
}
