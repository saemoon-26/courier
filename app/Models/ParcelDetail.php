<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelDetail extends Model
{
   protected $table = 'parcel_details';
protected $primaryKey = 'parcel_details_id';
public $timestamps = false;

protected $fillable = [
    'parcel_id', 'parcel_amount' ,'client_name', 'client_phone_number',
    'client_address', 'client_email'
];

public function parcel()
{
    return $this->belongsTo(Parcel::class, 'parcel_id', 'parcel_id');
}

}
