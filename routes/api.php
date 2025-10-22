<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PageElementController;
use Illuminate\Support\Facades\Artisan;


// ----------------------------
// Public routes (no auth) - MINIMAL SET
// ----------------------------

Route::post('/login', [AuthController::class, 'login']);
Route::get('/role', [RoleController::class, 'index']);


Route::middleware('auth:sanctum')->get('/token-check', fn() => response()->json(['valid' => true]));

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('permission:auth.store')->name('auth.register');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // ----------------------------
    // Users
    // ----------------------------
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.index')->name('users.index');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.show')->name('users.show');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.store')->name('users.store');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')->name('users.update');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.destroy')->name('users.destroy');

    // ----------------------------
    // Roles
    // ----------------------------
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.index')->name('roles.index');

    Route::get('/roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:roles.show')->name('roles.show');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.store')->name('roles.store');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.update')->name('roles.update');

    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.destroy')->name('roles.destroy');

    // ----------------------------
    // Permissions
    // ----------------------------
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:permissions.index')->name('permissions.index');

    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
        ->middleware('permission:permissions.show')->name('permissions.show');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:permissions.store')->name('permissions.store');

    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:permissions.update')->name('permissions.update');

    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('permission:permissions.destroy')->name('permissions.destroy');

    // ----------------------------
    // Unified Page Elements Routes
    // ----------------------------

    Route::get('/page-elements-menu', [PageElementController::class, 'myPages'])
        ->name('page-management');

    Route::get('/page-elements', [PageElementController::class, 'index'])
        ->middleware('permission:pages.index')->name('pages.index');

    // Add the new show route here
    Route::get('/page-elements/{pageElement}', [PageElementController::class, 'show'])
        ->middleware('permission:pages.show')->name('pages.show');

    Route::post('/page-elements', [PageElementController::class, 'store'])
        ->middleware('permission:pages.store')->name('pages.store');

    Route::put('/page-elements/{pageElement}', [PageElementController::class, 'update'])
        ->middleware('permission:pages.update')->name('pages.update');

    Route::delete('/page-elements/{pageElement}', [PageElementController::class, 'destroy'])
        ->middleware('permission:pages.destroy')->name('pages.destroy');

    Route::put('/page-elements/{pageElement}/roles', [PageElementController::class, 'updateRoles'])
        ->middleware('permission:pages.update')->name('pages.update-roles');


    // ----------------------------
    // Sync Permissions
    // ----------------------------
    Route::post('/AddRouteToPermission', function () {
        // We now only need to call the PermissionSeeder to sync everything
        Artisan::call('db:seed', [
            '--class' => 'PermissionSeeder',
        ]);
        Artisan::call('db:seed', [
            '--class' => 'RolesAndPermissionsSeeder',
        ]);
        return response()->json([
            'message' => 'Permissions synced successfully.'
        ]);
    })->middleware('permission:roles.sync-permissions')->name('roles.sync-permissions');
});
