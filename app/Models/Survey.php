<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $table = 'surveys';

    protected $fillable = [
        'sbu_id',
        'link_type_id',
        'aggregator_id',
        'kam_id',
        'nttn_id',
        'nttn_survey_id',
        'nttn_lat',
        'nttn_long',
        'client_lat',
        'client_long',
        'client_id',
        'mac_user',
        'submission',
        'posted_by',
        'status',
    ];

    protected $casts = [
        'sbu_id'         => 'integer',
        'submission'     => 'datetime',
        'nttn_lat'       => 'float',
        'nttn_long'      => 'float',
        'client_lat'     => 'float',
        'client_long'    => 'float',
        'mac_user'       => 'integer',
    ];
}
