<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageElement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PageElementController extends Controller
{
    /**
     * Display a listing of the page elements with their permissions and related roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Eager load permissions and their associated roles for each page element
            // Also eager load the roles relationship
            $pageElements = PageElement::with(['permissions.roles', 'roles'])->get();

            return response()->json([
                'page_elements' => $pageElements
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch page elements.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Get page elements accessible to the currently authenticated user.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function myPages(Request $request)
{
    try {
        $user = $request->user();
        $userRoleIds = $user->roles()->pluck('id')->toArray();

        // Only pages that the user has access to via roles
        $pageElements = PageElement::whereHas('roles', function ($query) use ($userRoleIds) {
            $query->whereIn('roles.id', $userRoleIds);
        })
        ->with(['roles', 'permissions.roles'])
        ->get();

        return response()->json([
            'page_elements' => $pageElements
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to fetch accessible page elements.',
            'error' => $e->getMessage()
        ], 500);
    }
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
            'menu_icon' => 'nullable|string|max:255',
            'sub_menu_name' => 'nullable|string|max:255',
            'sub_menu_icon' => 'nullable|string|max:255',
            'page_icon' => 'nullable|string|max:255',
            'status' => 'nullable|integer',
            'order_id' => 'nullable|integer',
        ]);

        // Automatically generate the page_slug from the path.
        $pathWithoutSlash = ltrim($validatedData['path'], '/');
        $validatedData['page_slug'] = Str::slug($pathWithoutSlash);

        // --- Custom ID Generation Logic ---

        // Find existing menu and sub-menu names to reuse their IDs.
        $existingMenu = PageElement::where('menu_name', $request->input('menu_name'))->first();
        $existingSubMenu = PageElement::where('sub_menu_name', $request->input('sub_menu_name'))->first();

        // Initialize new IDs to null.
        $newMenuId = null;
        $newSubMenuId = null;

        // Check if a menu name was provided.
        if ($request->filled('menu_name')) {
            if ($existingMenu) {
                // If the menu name exists, reuse its ID.
                $newMenuId = $existingMenu->menu_id;
            } else {
                // If not, generate a new ID.
                $lastMenuId = PageElement::max('menu_id');
                $newMenuId = ($lastMenuId === null) ? 1 : $lastMenuId + 1;
            }
        }

        // Check if a sub-menu name was provided.
        if ($request->filled('sub_menu_name')) {
            if ($existingSubMenu) {
                // If the sub-menu name exists, reuse its ID.
                $newSubMenuId = $existingSubMenu->sub_menu_id;
            } else {
                // If not, generate a new ID.
                $lastSubMenuId = PageElement::max('sub_menu_id');
                $newSubMenuId = ($lastSubMenuId === null) ? 1 : $lastSubMenuId + 1;
            }
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
        $pageElement = PageElement::create($validatedData);

        return response()->json($pageElement, 201);
    }

    /**
     * Display the specified page element with its permissions and roles.
     *
     * @param  \App\Models\PageElement  $pageElement
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(PageElement $pageElement)
    {
        try {
            // Eager load permissions and their associated roles, and the new roles relationship
            $pageElement->load(['permissions.roles', 'roles']);

            return response()->json([
                'page_element' => $pageElement
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch the specified page element.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

  /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PageElement  $pageElement
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, PageElement $pageElement)
    {
        try {
            // Case 1: Updating a Menu Name (bulk update)
            if ($request->filled('new_menu_name')) {
                PageElement::where('menu_id', $pageElement->menu_id)->update([
                    'menu_name' => $request->input('new_menu_name')
                ]);

                return response()->json([
                    'message' => 'Menu name updated successfully for all associated pages.'
                ], 200);
            }

            // Case 2: Updating a Submenu Name and/or reassigning to a new Menu (bulk update)
            if ($request->filled('new_sub_menu_name')) {
                // Get the new menu_id and menu_name from the request.
                $newMenuName = $request->input('menu_name');
                $newMenu = PageElement::where('menu_name', $newMenuName)->first();
                $newMenuId = $newMenu ? $newMenu->menu_id : (PageElement::max('menu_id') ?? 0) + 1;

                // Perform the bulk update for all elements under the old submenu
                PageElement::where('sub_menu_id', $pageElement->sub_menu_id)->update([
                    'sub_menu_name' => $request->input('new_sub_menu_name'),
                    'menu_id' => $newMenuId, // Assign the new menu ID
                    'menu_name' => $newMenuName // Assign the new menu name
                ]);

                return response()->json([
                    'message' => 'Submenu updated successfully and reassigned to the new menu.'
                ], 200);
            }

            // Case 3: Updating a Page (individual update)
            $validatedData = $request->validate([
                'page_name' => 'required|string|max:255',
                'path' => 'required|string|max:255',
                'menu_name' => 'nullable|string|max:255',
                'menu_icon' => 'nullable|string|max:255',
                'sub_menu_name' => 'nullable|string|max:255',
                'sub_menu_icon' => 'nullable|string|max:255',
                'page_icon' => 'nullable|string|max:255',
                'status' => 'nullable|integer',
                'order_id' => 'nullable|integer',
            ]);

            // Auto-generate slug from path
            $pathWithoutSlash = ltrim($validatedData['path'], '/');
            $validatedData['page_slug'] = Str::slug($pathWithoutSlash);

            // Handle Menu and Submenu Reassignment
            $newMenuId = $pageElement->menu_id;
            $newSubMenuId = $pageElement->sub_menu_id;

            // Check if menu assignment has changed
            if ($request->filled('menu_name') && $request->input('menu_name') !== $pageElement->menu_name) {
                $existingMenu = PageElement::where('menu_name', $request->input('menu_name'))->first();
                $newMenuId = $existingMenu ? $existingMenu->menu_id : (PageElement::max('menu_id') ?? 0) + 1;
            }

            // Check if submenu assignment has changed
            if ($request->filled('sub_menu_name') && $request->input('sub_menu_name') !== $pageElement->sub_menu_name) {
                $existingSubMenu = PageElement::where('sub_menu_name', $request->input('sub_menu_name'))->first();
                $newSubMenuId = $existingSubMenu ? $existingSubMenu->sub_menu_id : (PageElement::max('sub_menu_id') ?? 0) + 1;
            }

            // Update the page element
            $pageElement->update(array_merge($validatedData, [
                'menu_id' => $newMenuId,
                'sub_menu_id' => $newSubMenuId
            ]));

            return response()->json($pageElement->refresh(), 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update page element.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Updates the roles associated with a specific page element.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PageElement  $pageElement
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRoles(Request $request, PageElement $pageElement)
    {
        try {
            $validatedData = $request->validate([
                'role_ids' => 'array', // Changed from 'required|array'
                'role_ids.*' => 'integer|exists:roles,id',
            ]);

            // Correctly sync the roles on the page element.
            // If 'role_ids' is missing or null, sync will remove all roles.
            $pageElement->roles()->sync($validatedData['role_ids'] ?? []);

            // Reload the page element with the new roles to return an updated response
            $pageElement->load('roles');

            return response()->json([
                'message' => 'Page roles updated successfully.',
                'page_element' => $pageElement
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update page roles.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PageElement $pageElement)
    {
        $pageElement->delete();
        return response()->json(null, 204);
    }
}
