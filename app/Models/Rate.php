<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    protected $table = 'rates';


    protected $fillable = [
        'nttn_id',
        'bw_range_from',
        'bw_range_to',
        'rate',
        'start_date',
        'end_date',
    ];


    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'bw_range_from' => 'integer',
        'bw_range_to' => 'integer',
        'rate' => 'integer',
    ];


}
