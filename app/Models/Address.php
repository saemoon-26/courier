<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $table = 'address';

    protected $fillable = [
        'user_id', 'city', 'address', 'country', 'state', 'zipcode', 'latitude', 'longitude', 'zone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
