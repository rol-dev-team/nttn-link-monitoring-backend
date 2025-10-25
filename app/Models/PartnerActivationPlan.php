<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerActivationPlan extends Model
{
    use HasFactory;

    protected $table = 'partner_activation_plans';

    protected $fillable = [
        'work_order_id',
        'client_id',
        'int_routing_ip',
        'ggc_routing_ip',
        'fna_routing_ip',
        'bcdx_routing_ip',
        'mcdn_routing_ip',
        'nttn_vlan',
        'int_vlan',
        'ggn_vlan',
        'fna_vlan',
        'bcdx_vlan',
        'mcdn_vlan',
        'nas_ip',
        'nat_ip',
        'connected_ws_name',
        'chr_server',
        'sw_port',
        'nic_no',
        'asn',
        'status',
        'note',
    ];
}

