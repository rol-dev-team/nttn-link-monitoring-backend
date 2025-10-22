<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapacityAlertConfig extends Model
{
    use HasFactory;

    protected $table = 'capacity_alert_configs';

    protected $fillable = [
        'activation_plan_id',
        'max_threshold_mbps',
        'max_frequency_per_day',
        'max_consecutive_days',
        'min_threshold_mbps',
        'min_frequency_per_day',
        'min_consecutive_days',
    ];

    /**
     * Relationship: belongs to PartnerActivationPlan
     */
    public function activationPlan()
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}

