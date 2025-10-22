<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Router extends Model
{
    protected $connection = 'pgsql';
    protected $fillable = ['vendor_id', 'router_name', 'ip_address', 'location','port','username','password', 'status'];

    public $timestamps = true;

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }
}

