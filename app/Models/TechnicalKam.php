<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicalKam extends Model
{
    use HasFactory;

    protected $table = 'technical_kams';

    protected $fillable = [
        'name',
        'designation',
        'mobile_no',
        'status',
    ];
}

