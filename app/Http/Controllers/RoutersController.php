<?php

namespace App\Http\Controllers;

use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // Still needed for the 'use' statement

class RoutersController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $query = Router::query();

        // Apply filters if provided
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('host_name')) {
            $hostname_input = $request->input('host_name');
            if (strtolower($hostname_input) === 'null') {
                $query->whereNull('host_name');
            } else {
                $query->where('host_name', 'ilike', '%' . $hostname_input . '%');
            }
        }

        if ($request->has('location')) {
            $query->where('location', 'ilike', '%' . $request->input('location') . '%');
        }

        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->input('ip_address'));
        }

        // --- FIX START ---
        // 1. Read the 'limit' parameter (which the frontend sends) or fall back to 'per_page'.
        // 2. Default to 10 if neither is set, but 500 will be used when requested.
        $limit = $request->query('limit', $request->query('per_page', 10));

        // Use the determined limit value for pagination
        $routers = $query->paginate($limit);
        // --- FIX END ---

        return response()->json([
            'success' => true,
            'data' => $routers->items(),
            'meta' => [
                'current_page' => $routers->currentPage(),
                'last_page' => $routers->lastPage(),
                // 'per_page' will now correctly show the requested limit (e.g., 500)
                'per_page' => $routers->perPage(),
                'total' => $routers->total(),
            ],
            'message' => 'Filtered routers retrieved successfully'
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        
         try {
            // $fillableData = $request->only(['vendor_id', 'ip_address', 'status', 'location', 'display_name']);
            $router = Router::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $router,
                'message' => 'Router created successfully',
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create router',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $routers = Router::find($id);

        if (!$routers) {
            return response()->json([
                'success' => false,
                'message' => 'Router not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $routers,
            'message' => 'Router retrieved successfully'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $routers = Router::find($id);

        if (!$routers) {
            return response()->json([
                'success' => false,
                'message' => 'Router not found'
            ], 404);
        }

        // Validation removed previously, leaving it out for consistency, but
        // it should ideally be here as well, adjusting the 'unique' rule to ignore the current ID.

        $routers->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $routers,
            'message' => 'Router updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $routers = Router::find($id);

        if (!$routers) {
            return response()->json([
                'success' => false,
                'message' => 'Router not found'
            ], 404);
        }

        $routers->delete();

        return response()->json([
            'success' => true,
            'message' => 'Router deleted successfully'
        ]);
    }
}
