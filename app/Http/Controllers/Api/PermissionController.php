<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return response()->json(['permissions' => $permissions], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => config('auth.defaults.guard')
        ]);

        return response()->json(['message' => 'Permission created successfully.', 'permission' => $permission], 201);
    }

    public function show(Permission $permission)
    {
        return response()->json(['permission' => $permission], 200);
    }

    public function update(Request $request, Permission $permission)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $permission->update(['name' => $request->name]);

        return response()->json(['message' => 'Permission updated successfully.', 'permission' => $permission], 200);
    }

    public function destroy(Permission $permission)
    {
        try {
            // detach from all roles
            $permission->roles()->detach();

            // ensure guard_name is set
            if (!$permission->guard_name) {
                $permission->guard_name = config('auth.defaults.guard');
                $permission->save();
            }

            $permission->delete();

            return response()->json(['message' => 'Permission deleted successfully.'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
