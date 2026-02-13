<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderRegistration extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'full_name', 'father_name', 'email', 'password', 'mobile_primary', 'mobile_alternate',
        'cnic_number', 'vehicle_type', 'vehicle_brand', 'vehicle_model',
        'vehicle_registration', 'driving_license_number', 'city', 'state',
        'address', 'bank_name', 'account_number',
        'account_title', 'profile_picture', 'cnic_front_image', 'cnic_back_image',
        'driving_license_image', 'vehicle_registration_book', 'vehicle_image',
        'electricity_bill', 'status', 'rejection_reason'
    ];
}
