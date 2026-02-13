<?php

use Illuminate\Support\Facades\Route;
use App\Models\RiderRegistration;
use Illuminate\Http\Request;

Route::post('/test-rider', function(Request $request) {
    try {
        $data = [
            'full_name' => 'Test Rider',
            'email' => 'test' . time() . '@example.com',
            'mobile_primary' => '03001234567',
            'city' => 'Karachi',
            'state' => 'Sindh',
            'address' => 'Test Address',
            'vehicle_type' => 'Bike'
        ];
        
        $registration = RiderRegistration::create($data);
        
        return response()->json([
            'success' => true,
            'data' => $registration
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});