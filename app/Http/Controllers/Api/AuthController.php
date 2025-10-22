<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PageElement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle a user registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Validate incoming request data, including new fields
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'primary_role_id' => 'required|integer',
            'team_id' => 'required|integer',
            'dept_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Error.', 'errors' => $validator->errors()], 422);
        }

        // Create the new user with new fields and formatted name
        $user = User::create([
            'name' => $request->name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'primary_role_id' => $request->primary_role_id,
            'team_id' => $request->team_id,
            'dept_id' => $request->dept_id,
        ]);

        // Eagerly load the 'roles' and their nested 'permissions' relationships
        $user->load('roles.permissions');

        // Generate a new Sanctum token for the user
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Handle a login request to the application using a username.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Error.', 'errors' => $validator->errors()], 422);
        }

        // Attempt to authenticate the user using the 'name' field
        if (!Auth::attempt(['name' => $request->name, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Get the authenticated user and eagerly load the 'roles' and their nested 'permissions' relationship
        $user = Auth::user()->load('roles.permissions');

        // Get the IDs of the authenticated user's roles
        $userRoleIds = $user->roles->pluck('id');

        // Revoke any existing tokens to prevent multiple active tokens
        // $user->tokens()->delete();
        // $user->tokens()->latest()->skip(3)->take(PHP_INT_MAX)->delete();


        // Generate a new Sanctum token for the user
        $token = $user->createToken('authToken')->plainTextToken;

        // Fetch only the PageElement objects that have a relationship with the user's roles
        $pageElements = PageElement::whereHas('roles', function ($query) use ($userRoleIds) {
            $query->whereIn('roles.id', $userRoleIds);
        })->with('roles')->get();

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $user,
            'token' => $token,
            'page_elements' => $pageElements,
        ], 200);
    }

    /**
     * Handle a logout request for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }
}
