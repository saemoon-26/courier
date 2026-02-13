<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeocodingService
{
    public function geocodeAddress($address)
    {
        // Check cache first
        $cacheKey = 'geocode_' . md5($address);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Method 1: Try Nominatim with proper delay
            sleep(1); // Respect rate limit
            $result = $this->tryNominatim($address);
            
            if ($result) {
                Cache::put($cacheKey, $result, 86400); // Cache for 24 hours
                return $result;
            }

            // Method 2: Try Photon (another free OSM-based service)
            $result = $this->tryPhoton($address);
            
            if ($result) {
                Cache::put($cacheKey, $result, 86400);
                return $result;
            }

        } catch (\Exception $e) {
            \Log::error('Geocoding error: ' . $e->getMessage());
        }
        
        return null;
    }

    private function tryNominatim($address)
    {
        try {
            // Extract city from address for better results
            $city = '';
            $addressLower = strtolower($address);
            
            if (strpos($addressLower, 'faisalabad') !== false) {
                $city = 'Faisalabad';
            } elseif (strpos($addressLower, 'lahore') !== false) {
                $city = 'Lahore';
            } elseif (strpos($addressLower, 'karachi') !== false) {
                $city = 'Karachi';
            } elseif (strpos($addressLower, 'islamabad') !== false) {
                $city = 'Islamabad';
            }
            
            // Build search query with city priority
            $searchAddress = $city ? $address . ', ' . $city . ', Pakistan' : $address . ', Pakistan';
            
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'CourierDeliveryApp/1.0 (contact@example.com)',
                    'Accept-Language' => 'en'
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $searchAddress,
                    'limit' => 1,
                    'countrycodes' => 'pk',
                    'addressdetails' => 1,
                    'bounded' => 1
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return [
                        'latitude' => $data[0]['lat'],
                        'longitude' => $data[0]['lon'],
                        'display_name' => $data[0]['display_name'] ?? $address
                    ];
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
            $response = Http::timeout(15)
                ->get('https://photon.komoot.io/api/', [
                    'q' => $address . ', Pakistan',
                    'limit' => 1
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data['features']) && isset($data['features'][0]['geometry']['coordinates'])) {
                    $coords = $data['features'][0]['geometry']['coordinates'];
                    return [
                        'latitude' => (string)$coords[1],
                        'longitude' => (string)$coords[0],
                        'display_name' => $data['features'][0]['properties']['name'] ?? $address
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Photon failed: ' . $e->getMessage());
        }
        
        return null;
    }
}
