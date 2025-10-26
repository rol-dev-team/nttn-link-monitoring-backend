<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NasDiskUsage extends Model
{
    protected $table = 'nas_disk_usages';

    protected $fillable = [
        'activation_plan_id',
        'disk_size',
        'disk_used',
        'collected_at',
    ];

    protected $casts = [
        'disk_size' => 'integer',
        'disk_used' => 'integer',
        'collected_at' => 'datetime',
    ];

    public function activationPlan(): BelongsTo
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
