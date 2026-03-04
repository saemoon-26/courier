<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderVehicle extends Model
{
    protected $fillable = [
        'rider_id',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_registration',
        'registration_no',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
