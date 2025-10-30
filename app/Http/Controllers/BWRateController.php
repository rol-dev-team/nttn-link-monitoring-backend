<?php

namespace App\Http\Controllers;

use App\Models\Rate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BWRateController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Pagination parameters
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);
            $offset = ($page - 1) * $limit;

            // Total count (for pagination)
            $totalCount = DB::table('rates')
                ->count();

            // Fetch paginated data with join
            $rates = DB::table('rates')
                ->join('add_rate_nttn as nttn', 'nttn.id', '=', 'rates.nttn_id')
                ->select('rates.*', 'nttn.nttn_name')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Rates fetched successfully',
                'data' => $rates,
                'totalCount' => $totalCount,
                'currentPage' => (int) $page,
                'perPage' => (int) $limit,
                'lastPage' => ceil($totalCount / $limit),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            $survey = Rate::create($request->all());
            return response()->json([
                'message' => 'Rate successfully recorded!',
                'survey' => $survey
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while saving the survey.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
