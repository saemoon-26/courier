<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RiderBank extends Model
{
    protected $fillable = [
        'rider_id',
        'bank_name',
        'account_number',
        'account_title',
    ];

    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => decrypt($value),
            set: fn ($value) => encrypt($value),
        );
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
