<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    // public function index()
    // {
    //     $roles = Role::with('permissions')->get();
    //     return response()->json(['roles' => $roles], 200);
    // }


    public function index()
{
    $roles = (new Role)->setConnection('mysql')->with('permissions')->get();
    return response()->json(['roles' => $roles], 200);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => config('auth.defaults.guard')
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json(['message' => 'Role created successfully.', 'role' => $role->load('permissions')], 201);
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        return response()->json(['role' => $role], 200);
    }

    public function update(Request $request, Role $role)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role->update(['name' => $request->name]);

        // This is the key part of the update: we synchronize the permissions.
        // Your original code already correctly implements this.
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json(['message' => 'Role updated successfully.', 'role' => $role->load('permissions')], 200);
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return response()->json(['message' => 'The admin role cannot be deleted.'], 403);
        }

        try {
            $role->syncPermissions([]); // detach all permissions
            $role->delete();

            return response()->json(['message' => 'Role deleted successfully.'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
