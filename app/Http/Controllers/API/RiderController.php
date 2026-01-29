<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Parcel;
use App\Models\ParcelDetail;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    // GET /api/riders/{id} - Get single rider by ID
    public function show($id)
    {
        $rider = User::where('role', 'rider')
            ->where('id', $id)
            ->with('address')
            ->first();

        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'Rider not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $rider->id,
                'first_name' => $rider->first_name,
                'last_name' => $rider->last_name,
                'email' => $rider->email,
                'phone_number' => $rider->phone,
                'rating' => $rider->rating ?? 5.0,
                'address' => $rider->address
            ]
        ]);
    }

    // GET /api/riders/{id}/parcels - Get all parcels for specific rider
    public function parcels($id)
    {
        $rider = User::where('role', 'rider')->where('id', $id)->first();

        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'Rider not found'
            ], 404);
        }

        $parcels = Parcel::where('assigned_to', $id)
            ->with('details')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($parcel) {
                return [
                    'id' => $parcel->parcel_id,
                    'tracking_code' => $parcel->tracking_code,
                    'pickup_location' => $parcel->pickup_location,
                    'pickup_city' => $parcel->pickup_city,
                    'dropoff_location' => $parcel->dropoff_location,
                    'dropoff_city' => $parcel->dropoff_city,
                    'parcel_status' => $parcel->parcel_status,
                    'payment_method' => $parcel->payment_method,
                    'rider_payout' => $parcel->rider_payout,
                    'company_payout' => $parcel->company_payout,
                    'client_name' => $parcel->details->client_name ?? null,
                    'client_phone_number' => $parcel->details->client_phone_number ?? null,
                    'client_address' => $parcel->details->client_address ?? null,
                    'client_email' => $parcel->details->client_email ?? null,
                    'assigned_to' => $parcel->assigned_to,
                    'created_at' => $parcel->created_at,
                    'updated_at' => $parcel->updated_at
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $parcels
        ]);
    }
}
