<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'sbu_id',
        'link_type_id',
        'aggregator_id',
        'kam_id',
        'nttn_id',
        'nttn_survey_id',
        'nttn_lat',
        'nttn_long',
        'client_id',
        'client_lat',
        'client_long',
        'mac_user',
        'nttn_work_order_id',
        'request_capacity',
        'shift_capacity',
        'current_capacity',
        'rate_id',
        'work_order_mac_user',
        'submission',
        'requested_delivery',
        'service_handover',
        'posted_by',
        'modify_status',
        'vlan',
        'status',
        'remarks',
    ];
}
