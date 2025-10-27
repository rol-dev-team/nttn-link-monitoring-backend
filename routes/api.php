<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PageElementController;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\TechnicalKamController;
use App\Http\Controllers\RadiusServerIpController;
use App\Http\Controllers\PartnerInfoController;
use App\Http\Controllers\PartnerActivationPlanController;
use App\Http\Controllers\PartnerInterfaceConfigController;
use App\Http\Controllers\PartnerDropDeviceConfigController;
use App\Http\Controllers\CapacityAlertConfigController;
use App\Http\Controllers\IcmpAlertConfigController;

use App\Http\Controllers\RrdController;

// ----------------------------
// Public routes (no auth) - MINIMAL SET
// ----------------------------
Route::get('/rrd-utilidation', [RrdController::class, 'getPortData']);
Route::get('/rrd-cpu-utilization', [RrdController::class, 'getDeviceCpuData']);
Route::get('/rrd-ram-utilization', [RrdController::class, 'getMempoolPerformanceData']);
Route::get('/rrd-storage-utilization', [RrdController::class, 'getSystemDiskStorageData']);
Route::get('/rrd-icmp-utilization', [RrdController::class, 'getIcmpPerformanceData']);



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



    Route::get('/icmp-alert-configs', [IcmpAlertConfigController::class, 'index'])
        ->middleware('permission:icmp_alert_configs.index')
        ->name('icmp_alert_configs.index');

    Route::get('/icmp-alert-configs/{id}', [IcmpAlertConfigController::class, 'show'])
        ->middleware('permission:icmp_alert_configs.show')
        ->name('icmp_alert_configs.show');

    Route::post('/icmp-alert-configs', [IcmpAlertConfigController::class, 'store'])
        ->middleware('permission:icmp_alert_configs.store')
        ->name('icmp_alert_configs.store');

    Route::put('/icmp-alert-configs/{id}', [IcmpAlertConfigController::class, 'update'])
        ->middleware('permission:icmp_alert_configs.update')
        ->name('icmp_alert_configs.update');

    Route::delete('/icmp-alert-configs/{id}', [IcmpAlertConfigController::class, 'destroy'])
        ->middleware('permission:icmp_alert_configs.destroy')
        ->name('icmp_alert_configs.destroy');



    Route::get('/capacity-alert-configs', [CapacityAlertConfigController::class, 'index'])
        ->middleware('permission:capacity_alert_configs.index')
        ->name('capacity_alert_configs.index');

    Route::get('/capacity-alert-configs/{id}', [CapacityAlertConfigController::class, 'show'])
        ->middleware('permission:capacity_alert_configs.show')
        ->name('capacity_alert_configs.show');

    Route::post('/capacity-alert-configs', [CapacityAlertConfigController::class, 'store'])
        ->middleware('permission:capacity_alert_configs.store')
        ->name('capacity_alert_configs.store');

    Route::put('/capacity-alert-configs/{id}', [CapacityAlertConfigController::class, 'update'])
        ->middleware('permission:capacity_alert_configs.update')
        ->name('capacity_alert_configs.update');

    Route::delete('/capacity-alert-configs/{id}', [CapacityAlertConfigController::class, 'destroy'])
        ->middleware('permission:capacity_alert_configs.destroy')
        ->name('capacity_alert_configs.destroy');


    Route::get('/partner-drop-device-configs', [PartnerDropDeviceConfigController::class, 'index'])
        ->middleware('permission:partner_drop_device_configs.index')
        ->name('partner_drop_device_configs.index');

    Route::get('/partner-drop-device-configs/{id}', [PartnerDropDeviceConfigController::class, 'show'])
        ->middleware('permission:partner_drop_device_configs.show')
        ->name('partner_drop_device_configs.show');

    Route::post('/partner-drop-device-configs', [PartnerDropDeviceConfigController::class, 'store'])
        ->middleware('permission:partner_drop_device_configs.store')
        ->name('partner_drop_device_configs.store');

    Route::put('/partner-drop-device-configs/{id}', [PartnerDropDeviceConfigController::class, 'update'])
        ->middleware('permission:partner_drop_device_configs.update')
        ->name('partner_drop_device_configs.update');

    Route::delete('/partner-drop-device-configs/{id}', [PartnerDropDeviceConfigController::class, 'destroy'])
        ->middleware('permission:partner_drop_device_configs.destroy')
        ->name('partner_drop_device_configs.destroy');


    Route::get('/partner-interface-configs', [PartnerInterfaceConfigController::class, 'index'])
        ->middleware('permission:partner_interface_configs.index')
        ->name('partner_interface_configs.index');

    Route::get('/partner-interface-configs/{id}', [PartnerInterfaceConfigController::class, 'show'])
        ->middleware('permission:partner_interface_configs.show')
        ->name('partner_interface_configs.show');

    Route::post('/partner-interface-configs', [PartnerInterfaceConfigController::class, 'store'])
        ->middleware('permission:partner_interface_configs.store')
        ->name('partner_interface_configs.store');

    Route::put('/partner-interface-configs/{id}', [PartnerInterfaceConfigController::class, 'update'])
        ->middleware('permission:partner_interface_configs.update')
        ->name('partner_interface_configs.update');

    Route::delete('/partner-interface-configs/{id}', [PartnerInterfaceConfigController::class, 'destroy'])
        ->middleware('permission:partner_interface_configs.destroy')
        ->name('partner_interface_configs.destroy');

    Route::get('/nas-ips', [PartnerInterfaceConfigController::class, 'fetchNasIpLocal'])
        ->middleware('permission:partner_interface_configs.fetchNasIpLocal')
        ->name('partner_interface_configs.fetchNasIpLocal');




    Route::get('/partner-activation-plans', [PartnerActivationPlanController::class, 'index'])
        ->middleware('permission:partner_activation_plans.index')
        ->name('partner_activation_plans.index');

    Route::get('/partner-activation-plans/{id}', [PartnerActivationPlanController::class, 'show'])
        ->middleware('permission:partner_activation_plans.show')
        ->name('partner_activation_plans.show');

    Route::post('/partner-activation-plans', [PartnerActivationPlanController::class, 'store'])
        ->middleware('permission:partner_activation_plans.store')
        ->name('partner_activation_plans.store');

    Route::put('/partner-activation-plans/{id}', [PartnerActivationPlanController::class, 'update'])
        ->middleware('permission:partner_activation_plans.update')
        ->name('partner_activation_plans.update');

    Route::delete('/partner-activation-plans/{id}', [PartnerActivationPlanController::class, 'destroy'])
        ->middleware('permission:partner_activation_plans.destroy')
        ->name('partner_activation_plans.destroy');



    Route::get('/partner-infos', [PartnerInfoController::class, 'index'])
        ->middleware('permission:partner_infos.index')
        ->name('partner_infos.index');

    Route::get('/partner-infos/{id}', [PartnerInfoController::class, 'show'])
        ->middleware('permission:partner_infos.show')
        ->name('partner_infos.show');

    Route::post('/partner-infos', [PartnerInfoController::class, 'store'])
        ->middleware('permission:partner_infos.store')
        ->name('partner_infos.store');

    Route::put('/partner-infos/{id}', [PartnerInfoController::class, 'update'])
        ->middleware('permission:partner_infos.update')
        ->name('partner_infos.update');

    Route::delete('/partner-infos/{id}', [PartnerInfoController::class, 'destroy'])
        ->middleware('permission:partner_infos.destroy')
        ->name('partner_infos.destroy');



    Route::get('/radius-server-ips', [RadiusServerIpController::class, 'index'])
        ->middleware('permission:radius_server_ips.index')
        ->name('radius_server_ips.index');

    Route::get('/radius-server-ips/{id}', [RadiusServerIpController::class, 'show'])
        ->middleware('permission:radius_server_ips.show')
        ->name('radius_server_ips.show');

    Route::post('/radius-server-ips', [RadiusServerIpController::class, 'store'])
        ->middleware('permission:radius_server_ips.store')
        ->name('radius_server_ips.store');

    Route::put('/radius-server-ips/{id}', [RadiusServerIpController::class, 'update'])
        ->middleware('permission:radius_server_ips.update')
        ->name('radius_server_ips.update');

    Route::delete('/radius-server-ips/{id}', [RadiusServerIpController::class, 'destroy'])
        ->middleware('permission:radius_server_ips.destroy')
        ->name('radius_server_ips.destroy');



    Route::get('/technical-kams', [TechnicalKamController::class, 'index'])
        ->middleware('permission:technical_kams.index')
        ->name('technical_kams.index');

    Route::get('/technical-kams/{id}', [TechnicalKamController::class, 'show'])
        ->middleware('permission:technical_kams.show')
        ->name('technical_kams.show');

    Route::post('/technical-kams', [TechnicalKamController::class, 'store'])
        ->middleware('permission:technical_kams.store')
        ->name('technical_kams.store');


    Route::put('/technical-kams/{id}', [TechnicalKamController::class, 'update'])
        ->middleware('permission:technical_kams.update')
        ->name('technical_kams.update');

    Route::delete('/technical-kams/{id}', [TechnicalKamController::class, 'destroy'])
        ->middleware('permission:technical_kams.destroy')
        ->name('technical_kams.destroy');

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
