<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiderLocation;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
{
    // Start tracking - set tracking_active = 1
    public function startTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'required|string',
            'rider_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $parcel = Parcel::where('tracking_code', $request->tracking_code)->first();
        
        if (!$parcel) {
            return response()->json(['status' => false, 'message' => 'Invalid tracking code'], 404);
        }

        $parcel->tracking_active = 1;
        $parcel->parcel_status = 'out_for_delivery';
        $parcel->save();

        return response()->json(['status' => true, 'message' => 'Tracking started']);
    }

    // Stop tracking - set tracking_active = 0
    public function stopTracking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'required|string',
            'rider_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $parcel = Parcel::where('tracking_code', $request->tracking_code)->first();
        
        if (!$parcel) {
            return response()->json(['status' => false, 'message' => 'Invalid tracking code'], 404);
        }

        $parcel->tracking_active = 0;
        $parcel->save();

        return response()->json(['status' => true, 'message' => 'Tracking stopped']);
    }

    // Rider updates location (called every 5-10 seconds from rider app)
    public function updateRiderLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'required|string',
            'rider_id' => 'nullable|integer',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'parcel_id' => 'nullable|exists:parcel,parcel_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find parcel by tracking code
        $parcel = Parcel::where('tracking_code', $request->tracking_code)->first();
        
        if (!$parcel) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid tracking code'
            ], 404);
        }

        $riderId = $request->rider_id ?? $parcel->assigned_to;
        $parcelId = $request->parcel_id ?? $parcel->parcel_id;
        
        // Update parcel status to out_for_delivery
        if ($parcel->parcel_status !== 'out_for_delivery') {
            $parcel->parcel_status = 'out_for_delivery';
            $parcel->tracking_active = true;
            $parcel->save();
        }

        RiderLocation::create([
            'rider_id' => $riderId,
            'parcel_id' => $parcelId,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'recorded_at' => now()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully'
        ]);
    }

    // Customer gets rider's live location for their parcel
    public function getRiderLocation($parcelId)
    {
        $parcel = Parcel::find($parcelId);

        if (!$parcel) {
            return response()->json([
                'status' => false,
                'message' => 'Parcel not found'
            ], 404);
        }

        if (!$parcel->assigned_to) {
            return response()->json([
                'status' => false,
                'message' => 'No rider assigned yet'
            ], 404);
        }

        // Get latest location of the rider
        $location = RiderLocation::where('rider_id', $parcel->assigned_to)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$location) {
            return response()->json([
                'status' => false,
                'message' => 'Rider location not available'
            ], 404);
        }

        // Get rider details
        $rider = User::find($parcel->assigned_to);

        return response()->json([
            'status' => true,
            'data' => [
                'rider' => [
                    'id' => $rider->id,
                    'name' => $rider->first_name . ' ' . $rider->last_name,
                    'phone' => $rider->phone_number ?? 'N/A',
                    'rating' => $rider->rating ?? 5.0
                ],
                'location' => [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'updated_at' => $location->recorded_at->diffForHumans()
                ],
                'parcel' => [
                    'status' => $parcel->parcel_status,
                    'pickup_location' => $parcel->pickup_location
                ]
            ]
        ]);
    }

    // Get tracking by tracking code (for customer without login)
    public function trackByCode($trackingCode)
    {
        $parcel = Parcel::with('details')->where('tracking_code', $trackingCode)->first();

        if (!$parcel) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid tracking code'
            ], 404);
        }

        if ($parcel->parcel_status !== 'out_for_delivery' || !$parcel->tracking_active) {
            return response()->json([
                'status' => true,
                'tracking_available' => false,
                'message' => 'Live tracking will be available when rider starts delivery',
                'current_status' => $parcel->parcel_status
            ]);
        }

        if (!$parcel->assigned_to) {
            return response()->json([
                'status' => true,
                'tracking_available' => false,
                'message' => 'No rider assigned yet'
            ]);
        }

        $location = RiderLocation::where('rider_id', $parcel->assigned_to)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $rider = User::find($parcel->assigned_to);

        if (!$location) {
            return response()->json([
                'status' => true,
                'tracking_available' => false,
                'message' => 'Rider has not started tracking yet. Please wait...',
                'rider' => [
                    'name' => $rider->first_name . ' ' . $rider->last_name,
                    'phone' => $rider->phone_number ?? 'N/A'
                ],
                'parcel_status' => $parcel->parcel_status
            ]);
        }

        // Get client address coordinates from database (already geocoded during parcel creation)
        $clientDetails = $parcel->details;
        $destinationAddress = $clientDetails->client_address ?? 'N/A';
        
        // Use saved coordinates from database
        $destinationCoords = [
            'latitude' => $clientDetails->delivery_latitude ?? 31.4504,
            'longitude' => $clientDetails->delivery_longitude ?? 73.1350
        ];

        return response()->json([
            'status' => true,
            'tracking_available' => true,
            'data' => [
                'rider' => [
                    'name' => $rider->first_name . ' ' . $rider->last_name,
                    'phone' => $rider->phone_number ?? 'N/A'
                ],
                'rider_location' => [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'last_update' => $location->recorded_at->diffForHumans()
                ],
                'client_location' => [
                    'latitude' => $destinationCoords['latitude'],
                    'longitude' => $destinationCoords['longitude']
                ],
                'client_name' => $clientDetails->client_name ?? 'N/A',
                'client_address' => $destinationAddress,
                'parcel_status' => $parcel->parcel_status
            ]
        ]);
    }


}
