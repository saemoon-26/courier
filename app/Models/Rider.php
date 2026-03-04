<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rider extends Model
{
    protected $fillable = [
        'user_id',
        'father_name',
        'mobile_primary',
        'mobile_alternate',
        'cnic_number',
        'driving_license_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RiderDocument::class);
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(RiderVehicle::class);
    }

    public function bank(): HasOne
    {
        return $this->hasOne(RiderBank::class);
    }
}
