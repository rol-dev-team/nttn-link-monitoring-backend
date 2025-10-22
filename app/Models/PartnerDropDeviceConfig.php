<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerDropDeviceConfig extends Model
{
    use HasFactory;

    protected $table = 'partner_drop_device_configs';

    protected $fillable = [
        'activation_plan_id',
        'device_ip',
        'usage_vlan',
        'connected_port',
    ];

    /**
     * Relationship: belongs to PartnerActivationPlan
     */
    public function activationPlan()
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}

