<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\GeocodingService;
use Illuminate\Support\Facades\DB;

$geocoder = new GeocodingService();

echo "=== Populating Coordinates for Existing Data ===\n\n";

// 1. Update rider addresses
echo "1. Updating Rider Addresses...\n";
$addresses = DB::table('address')
    ->whereNull('latitude')
    ->whereNotNull('address')
    ->get();

echo "Found " . count($addresses) . " addresses to geocode\n";

foreach ($addresses as $addr) {
    echo "  - Geocoding: {$addr->address}, {$addr->city}...";
    
    try {
        $result = $geocoder->geocodeAddress($addr->address, $addr->city);
        
        if ($result && isset($result['latitude']) && isset($result['longitude'])) {
            DB::table('address')
                ->where('id', $addr->id)
                ->update([
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'updated_at' => now()
                ]);
            echo " OK ({$result['latitude']}, {$result['longitude']})\n";
        } else {
            echo " FAILED (no result)\n";
        }
    } catch (\Exception $e) {
        echo " ERROR: " . $e->getMessage() . "\n";
    }
    
    sleep(2); // Rate limit - 2 seconds
}

// 2. Update parcel pickup locations
echo "\n2. Updating Parcel Pickup Locations...\n";
$parcels = DB::table('parcel')
    ->whereNull('pickup_lat')
    ->whereNotNull('pickup_location')
    ->limit(10) // Limit to 10 for testing
    ->get();

echo "Found " . count($parcels) . " parcels to geocode\n";

foreach ($parcels as $parcel) {
    echo "  - Geocoding: {$parcel->pickup_location}, {$parcel->pickup_city}...";
    
    try {
        $result = $geocoder->geocodeAddress($parcel->pickup_location, $parcel->pickup_city);
        
        if ($result && isset($result['latitude']) && isset($result['longitude'])) {
            DB::table('parcel')
                ->where('parcel_id', $parcel->parcel_id)
                ->update([
                    'pickup_lat' => $result['latitude'],
                    'pickup_lng' => $result['longitude'],
                    'updated_at' => now()
                ]);
            echo " OK ({$result['latitude']}, {$result['longitude']})\n";
        } else {
            echo " FAILED (no result)\n";
        }
    } catch (\Exception $e) {
        echo " ERROR: " . $e->getMessage() . "\n";
    }
    
    sleep(2);
}

// 3. Update client addresses
echo "\n3. Updating Client Dropoff Addresses...\n";
$details = DB::table('parcel_details')
    ->whereNull('client_latitude')
    ->whereNotNull('client_address')
    ->limit(10) // Limit to 10 for testing
    ->get();

echo "Found " . count($details) . " client addresses to geocode\n";

foreach ($details as $detail) {
    echo "  - Geocoding: {$detail->client_address}...";
    
    try {
        $result = $geocoder->geocodeAddress($detail->client_address);
        
        if ($result && isset($result['latitude']) && isset($result['longitude'])) {
            DB::table('parcel_details')
                ->where('parcel_details_id', $detail->parcel_details_id)
                ->update([
                    'client_latitude' => $result['latitude'],
                    'client_longitude' => $result['longitude']
                ]);
            echo " OK ({$result['latitude']}, {$result['longitude']})\n";
        } else {
            echo " FAILED (no result)\n";
        }
    } catch (\Exception $e) {
        echo " ERROR: " . $e->getMessage() . "\n";
    }
    
    sleep(2);
}

echo "\n=== Coordinate Population Complete! ===\n";
echo "\nSummary:\n";
echo "- Rider addresses: " . DB::table('address')->whereNotNull('latitude')->count() . " geocoded\n";
echo "- Parcel pickups: " . DB::table('parcel')->whereNotNull('pickup_lat')->count() . " geocoded\n";
echo "- Client dropoffs: " . DB::table('parcel_details')->whereNotNull('client_latitude')->count() . " geocoded\n";
