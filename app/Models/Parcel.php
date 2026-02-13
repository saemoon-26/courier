<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    
protected $table = 'parcel';
protected $primaryKey = 'parcel_id';
public $timestamps = true;

protected $fillable = [
    'tracking_code', 'merchant_id', 'assigned_to',
    'pickup_location', 'pickup_city',
    'parcel_status','payment_method','rider_payout','company_payout','collected_by_rider',
    'tracking_active'
];

protected $casts = [
    'tracking_active' => 'boolean',
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
