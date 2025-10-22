<?php

namespace App\Http\Controllers;

use App\Models\RouterBwStatistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Router_bw_statisticsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RouterBwStatistic::query();

        // Optional filters
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->input('router_id'));
        }

        if ($request->filled('host_name')) {
            $query->where('host_name', 'ilike', '%' . $request->input('host_name') . '%');
        }

        if ($request->filled('interface')) {
            $query->where('interface', 'ilike', '%' . $request->input('interface') . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', 'ilike', '%' . $request->input('category') . '%');
        }

        if ($request->filled('category_type')) {
            $query->where('category_type', $request->input('category_type'));
        }

        // Pagination
        $perPage = $request->query('per_page', 10);
        $stats = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stats->items(),
            'meta' => [
                'current_page' => $stats->currentPage(),
                'last_page' => $stats->lastPage(),
                'per_page' => $stats->perPage(),
                'total' => $stats->total(),
            ],
            'message' => 'RouterBwStatistic list retrieved successfully'
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), RouterBwStatistic::rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $router_bw_statistics = RouterBwStatistic::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $router_bw_statistics,
            'message' => 'Router_bw_statistics created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $router_bw_statistics = RouterBwStatistic::find($id);

        if (!$router_bw_statistics) {
            return response()->json([
                'success' => false,
                'message' => 'Router_bw_statistics not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $router_bw_statistics,
            'message' => 'Router_bw_statistics retrieved successfully'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $router_bw_statistics = RouterBwStatistic::find($id);

        if (!$router_bw_statistics) {
            return response()->json([
                'success' => false,
                'message' => 'Router_bw_statistics not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), RouterBwStatistic::rules($id));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $router_bw_statistics->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $router_bw_statistics,
            'message' => 'Router_bw_statistics updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $router_bw_statistics = RouterBwStatistic::find($id);

        if (!$router_bw_statistics) {
            return response()->json([
                'success' => false,
                'message' => 'Router_bw_statistics not found'
            ], 404);
        }

        $router_bw_statistics->delete();

        return response()->json([
            'success' => true,
            'message' => 'Router_bw_statistics deleted successfully'
        ]);
    }
}
