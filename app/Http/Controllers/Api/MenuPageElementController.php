<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuPageElement;
use Illuminate\Http\Request;

class MenuPageElementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pageElements = MenuPageElement::all();
        return response()->json($pageElements);
    }

    /**
     * Store a newly created resource in storage.
     * Handles custom logic for generating menu_id and sub_menu_id.
     */
    public function store(Request $request)
    {
        // Validate the incoming request data.
        $validatedData = $request->validate([
            'page_name' => 'required|string|max:255',
            'path' => 'required|string|max:255',
            'menu_name' => 'nullable|string|max:255',
            'menu_icon' => 'nullable|string|max:255', // New validation rule
            'sub_menu_name' => 'nullable|string|max:255',
            'sub_menu_icon' => 'nullable|string|max:255', // New validation rule
            'page_icon' => 'nullable|string|max:255', // New validation rule
            'status' => 'nullable|integer', // New validation rule
        ]);

        // --- Custom ID Generation Logic ---

        // Initialize new IDs to null.
        $newMenuId = null;
        $newSubMenuId = null;

        // Check if a menu name was provided.
        if ($request->filled('menu_name')) {
            // Find the maximum existing menu_id.
            $lastMenuId = MenuPageElement::max('menu_id');
            // If none exist, start at 1. Otherwise, increment.
            $newMenuId = ($lastMenuId === null) ? 1 : $lastMenuId + 1;
        }

        // Check if a sub-menu name was provided.
        if ($request->filled('sub_menu_name')) {
            // Find the maximum existing sub_menu_id.
            $lastSubMenuId = MenuPageElement::max('sub_menu_id');
            // If none exist, start at 1. Otherwise, increment.
            $newSubMenuId = ($lastSubMenuId === null) ? 1 : $lastSubMenuId + 1;
        }

        // Add the newly generated IDs to the validated data.
        $validatedData['menu_id'] = $newMenuId;
        $validatedData['sub_menu_id'] = $newSubMenuId;

        // Set a default status if none is provided.
        if (!isset($validatedData['status'])) {
            $validatedData['status'] = 1;
        }

        // --- End of Custom ID Generation Logic ---

        // Create the new record in the database.
        $pageElement = MenuPageElement::create($validatedData);

        return response()->json($pageElement, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuPageElement $menuPageElement)
    {
        return response()->json($menuPageElement);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MenuPageElement $menuPageElement)
    {
        // Your specific update logic would go here.
        // For now, this is a placeholder.
        return response()->json($menuPageElement);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MenuPageElement $menuPageElement)
    {
        $menuPageElement->delete();
        return response()->json(null, 204);
    }
}
