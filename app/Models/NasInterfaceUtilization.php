<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NasInterfaceUtilization extends Model
{
    protected $table = 'nas_interface_utilization';

    protected $fillable = [
        'activation_plan_id',
        'interface_port',
        'max_download_mbps',
        'max_upload_mbps',
        'max_download_collected_at',
        'max_upload_collected_at',
    ];

    protected $casts = [
        'max_download_mbps' => 'double',
        'max_upload_mbps' => 'double',
        'max_download_collected_at' => 'datetime',
        'max_upload_collected_at' => 'datetime',
    ];

    public function activationPlan(): BelongsTo
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
