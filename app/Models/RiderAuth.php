<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class RiderAuth extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'riders_auth';

    protected $fillable = [
        'full_name', 'email', 'password', 'mobile_primary', 
        'city', 'state', 'vehicle_type', 'status'
    ];

    protected $hidden = [
        'password'
    ];
}