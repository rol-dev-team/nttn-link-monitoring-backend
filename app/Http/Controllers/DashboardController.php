<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
