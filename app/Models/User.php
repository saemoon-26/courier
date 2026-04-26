<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'location_id',
        'address_id',
        'company_id',
        'per_parcel_payout',
        'status',
        'profile_image',
    ];

    // Auto-load address relation when needed
    // protected $with = ['address'];

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function rider()
    {
        return $this->hasOne(Rider::class, 'user_id');
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
