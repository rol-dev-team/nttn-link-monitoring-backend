<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuPageElement extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'menu_page_elements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'page_name',
        'path',
        'sub_menu_id',
        'sub_menu_name',
        'sub_menu_icon', // New field
        'menu_id',
        'menu_name',
        'menu_icon', // New field
        'page_icon', // New field
        'status', // New field
    ];
}
