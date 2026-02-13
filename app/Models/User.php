<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'location_id',
        'address_id',
        'company_id',
        'per_parcel_payout',
        'rating',
        'status',
    ];

    // Auto-load address relation when needed
    // protected $with = ['address'];

    public function address(): HasOne
    {
        return $this->hasOne(Address::class)->withDefault();
    }

    public function parcels()
{
    return $this->hasMany(Parcel::class, 'assigned_to');
}

public function company()
{
    return $this->belongsTo(MerchantCompany::class, 'company_id');
}

}
