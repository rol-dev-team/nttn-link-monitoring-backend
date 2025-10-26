<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NasCpuUsage extends Model
{
    protected $table = 'nas_cpu_usages';

    protected $fillable = [
        'activation_plan_id',
        'max_cpu_load',
        'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
    ];

    public function activationPlan()
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
