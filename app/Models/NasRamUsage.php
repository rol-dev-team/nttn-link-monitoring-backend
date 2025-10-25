<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NasRamUsage extends Model
{
    protected $table = 'nas_ram_usages';

    protected $fillable = [
        'activation_plan_id',
        'max_memory_load',
        'collected_at',
    ];

    protected $casts = [
        'max_memory_load' => 'double',
        'collected_at' => 'datetime',
    ];

    public function activationPlan(): BelongsTo
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
