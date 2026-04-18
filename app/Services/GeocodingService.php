<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeocodingService
{
    /**
     * Geocode address with database caching
     * Returns: ['latitude' => float, 'longitude' => float, 'display_name' => string]
     */
    public function geocodeAddress($address, $city = null)
    {
        if (!$address) {
            return null;
        }

        // Build full address
        $fullAddress = $city ? "$address, $city" : $address;
        
        // Check memory cache first (fast)
        $cacheKey = 'geocode_' . md5(strtolower($fullAddress));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Try multiple geocoding strategies
            $result = $this->tryNominatim($fullAddress, $city);
            
            if (!$result) {
                $result = $this->tryPhoton($fullAddress);
            }
            
            if ($result) {
                // Cache for 30 days
                Cache::put($cacheKey, $result, 86400 * 30);
                return $result;
            }

        } catch (\Exception $e) {
            \Log::error('Geocoding error: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get or geocode rider address from address table
     */
    public function getRiderCoordinates($addressId)
    {
        $address = DB::table('address')->where('id', $addressId)->first();
        
        if (!$address) {
            return null;
        }

        // Return cached coordinates if available
        if ($address->latitude && $address->longitude) {
            return [
                'latitude' => (float)$address->latitude,
                'longitude' => (float)$address->longitude,
                'display_name' => $address->address
            ];
        }

        // Geocode and cache in database
        $result = $this->geocodeAddress($address->address, $address->city);
        
        if ($result) {
            DB::table('address')
                ->where('id', $addressId)
                ->update([
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'updated_at' => now()
                ]);
        }
        
        return $result;
    }

    /**
     * Get or geocode parcel pickup coordinates
     */
    public function getParcelPickupCoordinates($parcelId)
    {
        $parcel = DB::table('parcel')->where('parcel_id', $parcelId)->first();
        
        if (!$parcel) {
            return null;
        }

        // Return cached coordinates if available
        if ($parcel->pickup_lat && $parcel->pickup_lng) {
            return [
                'latitude' => (float)$parcel->pickup_lat,
                'longitude' => (float)$parcel->pickup_lng,
                'display_name' => $parcel->pickup_location
            ];
        }

        // Geocode and cache in database
        $result = $this->geocodeAddress($parcel->pickup_location, $parcel->pickup_city);
        
        if ($result) {
            DB::table('parcel')
                ->where('parcel_id', $parcelId)
                ->update([
                    'pickup_lat' => $result['latitude'],
                    'pickup_lng' => $result['longitude'],
                    'updated_at' => now()
                ]);
        }
        
        return $result;
    }

    /**
     * Get or geocode client dropoff coordinates
     */
    public function getClientCoordinates($parcelId)
    {
        $details = DB::table('parcel_details')->where('parcel_id', $parcelId)->first();
        
        if (!$details) {
            return null;
        }

        // Return cached coordinates if available
        if ($details->client_latitude && $details->client_longitude) {
            return [
                'latitude' => (float)$details->client_latitude,
                'longitude' => (float)$details->client_longitude,
                'display_name' => $details->client_address
            ];
        }

        // Geocode and cache in database
        $result = $this->geocodeAddress($details->client_address);
        
        if ($result) {
            DB::table('parcel_details')
                ->where('parcel_id', $parcelId)
                ->update([
                    'client_latitude' => $result['latitude'],
                    'client_longitude' => $result['longitude']
                ]);
        }
        
        return $result;
    }

    private function tryNominatim($address, $city = null)
    {
        try {
            // Try multiple search strategies
            $queries = [
                "$address, Pakistan",
                $city ? "$address, $city, Pakistan" : null,
                $city ? "$city, Pakistan" : null  // Fallback to city center
            ];
            
            foreach (array_filter($queries) as $query) {
                sleep(1); // Rate limit
                
                $response = Http::withoutVerifying() // Disable SSL verification for development
                    ->timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'CourierDeliveryApp/1.0 (contact@example.com)',
                        'Accept-Language' => 'en'
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'json',
                        'q' => $query,
                        'limit' => 1,
                        'countrycodes' => 'pk',
                        'addressdetails' => 1
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                        return [
                            'latitude' => (float)$data[0]['lat'],
                            'longitude' => (float)$data[0]['lon'],
                            'display_name' => $data[0]['display_name'] ?? $address
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Nominatim failed: ' . $e->getMessage());
        }
        
        return null;
    }

    private function tryPhoton($address)
    {
        try {
            $response = Http::withoutVerifying() // Disable SSL verification for development
                ->timeout(15)
                ->get('https://photon.komoot.io/api/', [
                    'q' => $address . ', Pakistan',
                    'limit' => 1
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['features']) && isset($data['features'][0]['geometry']['coordinates'])) {
                    $coords = $data['features'][0]['geometry']['coordinates'];
                    return [
                        'latitude' => (float)$coords[1],
                        'longitude' => (float)$coords[0],
                        'display_name' => $data['features'][0]['properties']['name'] ?? $address
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Photon failed: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return null;
        }

        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
