<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerInterfaceConfig extends Model
{
    use HasFactory;

    protected $table = 'partner_interface_configs';

    protected $fillable = [
        'activation_plan_id',
        'interface_name',
        'interface_port'
    ];

    /**
     * Relationship: belongs to PartnerActivationPlan
     */
    public function activationPlan()
    {
        return $this->belongsTo(PartnerActivationPlan::class, 'activation_plan_id');
    }
}
