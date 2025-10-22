<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerInfo extends Model
{
    use HasFactory;

    protected $table = 'partner_infos';

    protected $fillable = [
        'word_order_id',
        'network_code',
        'address',
        'contact_number',
        'router_identity',
        'technical_kam_id',
        'radius_server_id',
    ];

    public function technicalKam()
    {
        return $this->belongsTo(TechnicalKam::class, 'technical_kam_id');
    }


    public function radiusServer()
    {
        return $this->belongsTo(RadiusServerIp::class, 'radius_server_id');
    }
}

