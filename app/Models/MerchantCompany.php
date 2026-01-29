<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'address',
        'per_parcel_rate',
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }

    // If you want to use created_at and updated_at
    public $timestamps = true;
}
