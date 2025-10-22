<?php

namespace Database\Seeders;

use App\Models\PageElement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all registered routes
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $name = $route->getName();
            $uri  = $route->uri();

            // We will only process named API routes
            if ($name && Str::startsWith($uri, 'api/')) {
                // Determine the page slug from the route name (e.g., 'products' from 'products.index')
                // This assumes a `resource.action` naming convention.
                $pageSlug = Str::before($name, '.');

                // If the slug is not a simple word (e.g., `auth.logout`), we'll skip it
                // This ensures we only create pages for logical resource groups.
                if (strpos($pageSlug, '.') === false) {

                    // Find or create the logical page element
                    // The page_name is a human-readable title generated from the slug
                    $pageElement = PageElement::firstOrCreate(
                        ['page_slug' => $pageSlug],
                        [
                            'page_name' => Str::title(Str::replace('-', ' ', $pageSlug)),
                            'path'      => '/' . $pageSlug, // Populates the path field
                            // You could also add default values for menu_name, etc. here if needed
                            'menu_name' => 'Super Admin',
                            'menu_id'  => 1,
                            'menu_icon'     => 'fa-solid fa-lock',

                        ]
                    );

                    // Find or create the Spatie permission for the full route name
                    $permission = Permission::firstOrCreate([
                        'name'       => $name,
                        'guard_name' => 'sanctum',
                    ]);

                    // Sync the permission to the page element using the pivot table.
                    // syncWithoutDetaching ensures existing permissions are not removed.
                    $pageElement->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }

        $this->command->info('API route permissions synced with pages successfully.');
    }
}
