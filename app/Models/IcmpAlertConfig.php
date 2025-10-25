<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcmpAlertConfig extends Model
{
    use HasFactory;

    protected $table = 'icmp_alert_configs';

    protected $fillable = [
        'activation_plan_id',
        'latency_threshold_ms',
//        'is_active',
    ];

//    protected $casts = [
//        'is_active' => 'boolean',
//    ];

    public function activationPlan()
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
