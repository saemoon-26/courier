<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelRiderRequest extends Model
{
    protected $table = 'parcel_rider_requests';
    
    public $timestamps = false;

    protected $fillable = [
        'parcel_id',
        'rider_id',
        'request_status',
        'rider_score',
        'sent_at',
        'responded_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'rider_score' => 'decimal:2'
    ];

    public function parcel()
    {
        return $this->belongsTo(Parcel::class, 'parcel_id', 'parcel_id');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
