<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Parcel;
use App\Models\RiderLocation;
use App\Services\GeocodingService;

class RiderDashboardController extends Controller
{
    // Get all assigned parcels for rider
    public function getAssignedParcels($riderId)
    {
        $parcels = Parcel::with('details')
            ->where('assigned_to', $riderId)
            ->whereIn('parcel_status', ['assigned', 'picked_up', 'out_for_delivery'])
            ->get();

        $result = $parcels->map(function($parcel) {
            $destinationAddress = $parcel->details->client_address ?? ($parcel->dropoff_location . ', ' . $parcel->dropoff_city);
            
            return [
                'parcel_id' => $parcel->parcel_id,
                'tracking_code' => $parcel->tracking_code,
                'status' => $parcel->parcel_status,
                'tracking_active' => $parcel->tracking_active ?? false,
                'client_name' => $parcel->details->client_name ?? 'N/A',
                'client_phone' => $parcel->details->client_phone_number ?? 'N/A',
                'client_address' => $destinationAddress,
                'destination_lat' => $parcel->details->delivery_latitude,
                'destination_lng' => $parcel->details->delivery_longitude,
                'pickup_location' => $parcel->pickup_location,
                'rider_payout' => $parcel->rider_payout
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }

    // Start tracking - Rider clicks "Start Tracking" button
    public function startTracking($parcelId)
    {
        $parcel = Parcel::where('parcel_id', $parcelId)->first();

        if (!$parcel) {
            return response()->json([
                'status' => false,
                'message' => 'Parcel not found'
            ], 404);
        }

        $parcel->tracking_active = true;
        $parcel->parcel_status = 'out_for_delivery';
        $parcel->save();

        // Get destination coordinates
        $destinationAddress = $parcel->details->client_address ?? ($parcel->dropoff_location . ', ' . $parcel->dropoff_city);
        
        if ($parcel->details && $parcel->details->delivery_latitude && $parcel->details->delivery_longitude) {
            $destination = [
                'latitude' => $parcel->details->delivery_latitude,
                'longitude' => $parcel->details->delivery_longitude,
                'address' => $destinationAddress
            ];
        } else {
            $geocodingService = new GeocodingService();
            $coords = $geocodingService->geocodeAddress($destinationAddress);
            
            $destination = [
                'latitude' => $coords['latitude'] ?? 31.4504,
                'longitude' => $coords['longitude'] ?? 73.1350,
                'address' => $destinationAddress
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Tracking started successfully',
            'data' => [
                'parcel' => [
                    'id' => $parcel->parcel_id,
                    'tracking_code' => $parcel->tracking_code,
                    'status' => $parcel->parcel_status,
                    'tracking_active' => true
                ],
                'client' => [
                    'name' => $parcel->details->client_name ?? 'N/A',
                    'phone' => $parcel->details->client_phone_number ?? 'N/A',
                    'address' => $destinationAddress
                ],
                'destination' => $destination
            ]
        ]);
    }

    // Stop tracking
    public function stopTracking($parcelId)
    {
        $parcel = Parcel::where('parcel_id', $parcelId)->first();

        if (!$parcel) {
            return response()->json([
                'status' => false,
                'message' => 'Parcel not found'
            ], 404);
        }

        $parcel->tracking_active = false;
        $parcel->save();

        return response()->json([
            'status' => true,
            'message' => 'Tracking stopped'
        ]);
    }
}
