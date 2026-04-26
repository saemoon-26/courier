<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ParcelRiderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelRiderRequestController extends Controller
{
    // NEW: Get all rider requests for all parcels in one call
    public function getAllParcelRiderRequests()
    {
        $requests = ParcelRiderRequest::with('rider:id,first_name,last_name')
            ->get()
            ->groupBy('parcel_id')
            ->map(function($parcelRequests) {
                return $parcelRequests->map(function($request) {
                    return [
                        'rider_id' => $request->rider_id,
                        'rider_name' => $request->rider ? $request->rider->first_name . ' ' . $request->rider->last_name : 'Unknown',
                        'request_status' => $request->request_status,
                        'rider_score' => $request->rider_score,
                        'sent_at' => $request->sent_at
                    ];
                })->values();
            });

        return response()->json([
            'status' => true,
            'data' => $requests
        ]);
    }

    public function getParcelRiderRequests($parcelId)
    {
        $requests = ParcelRiderRequest::where('parcel_id', $parcelId)
            ->with('rider:id,first_name,last_name')
            ->get()
            ->map(function($request) {
                return [
                    'rider_id' => $request->rider_id,
                    'rider_name' => $request->rider ? $request->rider->first_name . ' ' . $request->rider->last_name : 'Unknown',
                    'request_status' => $request->request_status,
                    'rider_score' => $request->rider_score,
                    'sent_at' => $request->sent_at
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $requests
        ]);
    }
}
