<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PageElement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'page_name',
        'page_slug',
        'path',
        'sub_menu_id',
        'sub_menu_name',
        'sub_menu_icon',
        'menu_id',
        'menu_name',
        'menu_icon',
        'page_icon',
        'status',
        'order_id',
    ];

    /**
     * The permissions that belong to the PageElement.
     */
    public function permissions(): BelongsToMany
    {
        // Define the many-to-many relationship with Spatie's Permission model,
        // using our new page_permissions pivot table.
        return $this->belongsToMany(Permission::class, 'page_permissions', 'page_element_id', 'permission_id');
    }

    /**
     * The roles that belong to the PageElement.
     */
    public function roles(): BelongsToMany
    {
        // Define the many-to-many relationship with Spatie's Role model,
        // using our new page_roles pivot table.
        return $this->belongsToMany(Role::class, 'page_roles', 'page_element_id', 'role_id');
    }
}
