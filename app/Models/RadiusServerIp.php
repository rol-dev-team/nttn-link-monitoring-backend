<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadiusServerIp extends Model
{
    use HasFactory;

    protected $table = 'radius_server_ips';

    protected $fillable = [
        'server_name',
        'status',
    ];
}
