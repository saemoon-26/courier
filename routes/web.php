<?php

use Illuminate\Support\Facades\Route;
use App\Services\GeocodingService;

// Test route for geocoding
Route::get('/test-geocoding', function () {
    $geocoding = new GeocodingService();
    
    $addresses = [
        'Karachi, Pakistan',
        'Lahore, Pakistan',
        'Islamabad, Pakistan'
    ];
    
    $results = [];
    foreach ($addresses as $address) {
        $result = $geocoding->geocodeAddress($address);
        $results[] = [
            'address' => $address,
            'coordinates' => $result
        ];
    }
    
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

// API-only application - no web routes needed
