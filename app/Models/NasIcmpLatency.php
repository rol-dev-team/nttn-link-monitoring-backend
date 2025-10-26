<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NasIcmpLatency extends Model
{
    protected $table = 'nas_icmp_latency';

    protected $fillable = [
        'activation_plan_id',
        'threshold_exceeded_value',
        'collected_at',
    ];

    protected $casts = [
        'threshold_exceeded_value' => 'double',
        'collected_at' => 'datetime',
    ];

    public function activationPlan(): BelongsTo
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
