<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderLocation extends Model
{
    protected $table = 'rider_locations';
    public $timestamps = false;

    protected $fillable = [
        'rider_id',
        'parcel_id',
        'latitude',
        'longitude',
        'recorded_at'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'recorded_at' => 'datetime'
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function parcel()
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }
}
