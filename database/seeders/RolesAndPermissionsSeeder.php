<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\PageElement;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create or get admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'sanctum']);

        // Create or get regular user role
        $userRole = Role::firstOrCreate(['name' => 'user'], ['guard_name' => 'sanctum']);

        // Get all permissions from the database (after seeding routes)
        $allPermissions = Permission::all();

        $allPageElements = PageElement::all();

        foreach ($allPageElements as $pageElement) {
            $pageElement->roles()->sync([$adminRole->id]);
        }

        // Assign all permissions to admin
        $adminRole->syncPermissions($allPermissions);

        // Optionally assign a subset to regular users
        $regularPermissions = $allPermissions->filter(function ($permission) {
            // Example: only allow viewing products
            return $permission->name === 'products.index' || $permission->name === 'products.show';
        });
        $userRole->syncPermissions($regularPermissions);

        // Create the admin user
        $adminUser = User::firstOrCreate([
            'email' => 'admin@example.com'
        ], [
            'name' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'mobile' => '9999999999',
            'password' => Hash::make('Root@@web'),
            'primary_role_id' => $adminRole->id,
            'team_id' => 1,
            'dept_id' => 1,
        ]);
        $adminUser->assignRole($adminRole);
    }
}
