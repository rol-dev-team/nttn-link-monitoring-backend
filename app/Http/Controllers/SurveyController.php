<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{

    // public function index(Request $request)
    // {
    //     try {
    //         // Pagination params
    //         $page = $request->query('page', 1);
    //         $limit = $request->query('limit', 10);

    //         // Optional filters
    //         $query = Survey::query();

    //         if ($request->has('sbu_id') && $request->sbu_id) {
    //             $query->where('sbu_id', $request->sbu_id);
    //         }

    //         if ($request->has('link_type_id') && $request->link_type_id) {
    //             $query->where('link_type_id', $request->link_type_id);
    //         }

    //         if ($request->has('status') && $request->status) {
    //             $query->where('status', $request->status);
    //         }
    //         $surveys = $query->orderBy('created_at', 'desc')
    //             ->paginate($limit, ['*'], 'page', $page);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Surveys fetched successfully',
    //             'data' => $surveys->items(),
    //             'totalCount' => $surveys->total(),
    //             'currentPage' => $surveys->currentPage(),
    //             'perPage' => $surveys->perPage(),
    //             'lastPage' => $surveys->lastPage(),
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to fetch surveys',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function index(Request $request)
    {
        try {
            // Pagination params
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Base query with joins
            $query = Survey::select(
                'surveys.*',
                'c.client_name',
                'cat.cat_name',
                'nttn.nttn_name',
                'sbu.sbu_name',
                'lt.type_name',
                'k.kam_name',
                'agg.aggregator_name',
                'th.thana_name',
                'dis.district_name',
                'div.division_name'
            )
                ->join('add_client_sbu as sbu', 'sbu.id', '=', 'surveys.sbu_id')
                ->join('add_rate_nttn as nttn', 'nttn.id', '=', 'surveys.nttn_id')
                ->join('master_data_linktype as lt', 'lt.id', '=', 'surveys.link_type_id')
                ->join('master_data_kam as k', 'k.id', '=', 'surveys.kam_id')
                ->join('master_data_aggregator as agg', 'agg.id', '=', 'surveys.aggregator_id')
                ->join('add_client_client as c', 'c.id', '=', 'surveys.client_id')
                ->join('add_client_thana as th', 'th.id', '=', 'c.thana_id')
                ->join('add_client_district as dis', 'dis.id', '=', 'th.district_id')
                ->join('add_client_division as div', 'div.id', '=', 'dis.division_id')
                ->join('add_client_clientcategory as cat', 'cat.id', '=', 'c.cat_id');

            // Apply filters
            if ($request->has('sbu_id') && $request->sbu_id) {
                $query->where('surveys.sbu_id', $request->sbu_id);
            }

            if ($request->has('link_type_id') && $request->link_type_id) {
                $query->where('surveys.link_type_id', $request->link_type_id);
            }

            if ($request->has('status') && $request->status) {
                $query->where('surveys.status', $request->status);
            }

            // Paginate
            $surveys = $query->orderBy('surveys.created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'message' => 'Surveys fetched successfully',
                'data' => $surveys->items(),
                'totalCount' => $surveys->total(),
                'currentPage' => $surveys->currentPage(),
                'perPage' => $surveys->perPage(),
                'lastPage' => $surveys->lastPage(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch surveys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {


        try {
            DB::beginTransaction();
            $survey = Survey::create($request->all());
            SurveyHistory::create($request->all());
            DB::commit();

            return response()->json([
                'message' => 'Survey successfully recorded!',
                'survey' => $survey
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'An error occurred while saving the survey.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getClientDetailsByClientAndCategory(Request $request)
    {
        try {

            $clientId = $request->input('client_id');
            $categoryId = $request->input('category_id');

             if (!$clientId || !$categoryId) {
                return response()->json([
                'success' => false,
                'message' => 'client_id and category_id are required',
            ], 404);

        }

        // Raw SQL Query
        $result = DB::select("
            SELECT
                c.*,
                cat.cat_name,
                th.thana_name,
                dis.district_name,
                div.division_name
            FROM add_client_client c
            INNER JOIN add_client_thana th ON th.id = c.thana_id
            INNER JOIN add_client_district dis ON dis.id = th.district_id
            INNER JOIN add_client_division div ON div.id = dis.division_id
            INNER JOIN add_client_clientcategory cat ON cat.id = c.cat_id
            WHERE c.id = ? AND cat.id = ?
        ", [$clientId, $categoryId]);

        if (empty($result)) {
             return response()->json([
                'success' => false,
                'message' => 'No client found for given criteria',
            ], 404);
        }

            return response()->json([
                'success' => true,
                'message' => 'Surveys fetched successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch surveys',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
