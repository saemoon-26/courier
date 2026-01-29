<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelCode extends Model
{
    protected $fillable = [
        'parcel_id',
        'code'
    ];

    /**
     * Relationship with Parcel model
     */
    public function parcel()
    {
        return $this->belongsTo(Parcel::class, 'parcel_id', 'parcel_id');
    }

    /**
     * Generate a unique 4-digit random code
     * Ensures no duplicate codes exist in the database
     */
    public static function generateUniqueCode()
    {
        do {
            // Generate random 4-digit code (1000-9999)
            $code = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists()); // Check if code already exists
        
        return $code;
    }
}
