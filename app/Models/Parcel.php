<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    
protected $table = 'parcel';
protected $primaryKey = 'parcel_id';
public $timestamps = false;

protected $fillable = [
    'tracking_code', 'merchant_id', 'assigned_to',
    'pickup_location', 'pickup_city', 'dropoff_location', 'dropoff_city',
    'parcel_status','payment_method','rider_payout','company_payout','collected_by_rider',
];

public function details()
{
    return $this->hasOne(ParcelDetail::class, 'parcel_id', 'parcel_id');
}

/**
 * Relationship with ParcelCode model
 */
public function code()
{
    return $this->hasOne(ParcelCode::class, 'parcel_id', 'parcel_id');
}

public function rider()
{
    return $this->belongsTo(User::class, 'assigned_to');
}

}
