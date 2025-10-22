<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterBwStatisticLog extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'router_bw_statistics_logs';

    protected $fillable = [
        'vendor_id','router_id','host_name','interface',
        'category','category_type','interface_description',
        'assigned_capacity','policer','utilization_mb','collected_at'
    ];

    public $timestamps = true;

    public function vendor() {
        return $this->belongsTo(Vendor::class);
    }

    public function router() {
        return $this->belongsTo(Router::class);
    }
}

