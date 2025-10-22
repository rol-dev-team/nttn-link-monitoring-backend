<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $connection = 'pgsql';
    protected $fillable = ['vendor_name', 'status'];

    public $timestamps = true;

    public function routers() {
        return $this->hasMany(Router::class);
    }
}

