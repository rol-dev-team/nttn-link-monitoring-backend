<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NasIcmpTimeout extends Model
{
    protected $table = 'nas_icmp_timeout';

    protected $fillable = [
        'activation_plan_id',
        'timeout_start',
        'timeout_end',
        'timeout_duration',
    ];

    protected $casts = [
        'timeout_start' => 'datetime',
        'timeout_end' => 'datetime',
        'timeout_duration' => 'integer',
    ];

    public function activationPlan(): BelongsTo
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
