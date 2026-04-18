<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Parcel;
use App\Models\ParcelDetail;
use App\Models\ParcelCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\ParcelCodeMail;
use App\Mail\DeliveryRequestMail;
use App\Services\Top3RiderService;

class ParcelController extends Controller
{
    // ✅ Generate unique tracking code
    public function generateTrackingCode()
    {
        $trackingCode = 'TRK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        return response()->json([
            'status' => true,
            'tracking_code' => $trackingCode
        ]);
    }

    // ✅ GET: All Parcels with client details
    public function getAllParcels()
    {
        $parcels = Parcel::with('details')->get()->map(function($parcel) {
            $rider = $parcel->assigned_to ? \App\Models\User::find($parcel->assigned_to) : null;
            return [
                'parcel_id' => $parcel->parcel_id,
                'tracking_code' => $parcel->tracking_code,
                'merchant_id' => $parcel->merchant_id,
                'assigned_to' => $parcel->assigned_to,
                'assigned_rider_name' => $rider ? ($rider->first_name . ' ' . $rider->last_name) : 'Pending Assignment',
                'pickup_location' => $parcel->pickup_location,
                'pickup_city' => $parcel->pickup_city,
                'dropoff_location' => $parcel->dropoff_location,
                'dropoff_city' => $parcel->dropoff_city,
                'parcel_status' => $parcel->parcel_status,
                'payment_method' => $parcel->payment_method,
                'rider_payout' => $parcel->rider_payout,
                'company_payout' => $parcel->company_payout,
                'collected_by_rider' => $parcel->collected_by_rider,
                'details' => $parcel->details
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $parcels
        ]);
    }

    // ✅ POST: Create new parcel with client details
    public function store(Request $request)
{

    // Validation
    $validator = Validator::make($request->all(), [
        'tracking_code' => 'nullable|unique:parcel,tracking_code',
        'merchant_id' => 'nullable|integer|exists:users,id',
        'assigned_to' => 'nullable|integer|exists:users,id',
        'pickup_location' => 'required|string',
        'pickup_city' => 'required|string',
        'dropoff_city' => 'nullable|string',
        'parcel_status' => 'nullable|in:pending,pickup_requested,picked_up,out_for_delivery,delivered,cancelled',
        'payment_method' => 'required|in:cod,online',
        'rider_payout' => 'nullable|numeric|min:0',
        'company_payout' => 'nullable|numeric|min:0',
        'company_id' => 'nullable|integer|exists:merchant_companies,id',

        'client_name' => 'required|string',
        'client_phone_number' => 'required|string',
        'client_address' => 'required|string',
        'client_email' => 'nullable|email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Get merchant_id from request
    $merchant_id = $request->merchant_id ?? null;
    
    // If company_id is provided instead, find merchant by company
    if (!$merchant_id && $request->company_id) {
        $merchant = \App\Models\User::where('company_id', $request->company_id)->first();
        $merchant_id = $merchant ? $merchant->id : null;
    }
    
    $company_payout = $request->company_payout ?? 0;
    $rider_payout = $request->rider_payout ?? 0;
    $assigned_to = $request->assigned_to;

    DB::beginTransaction();
    try {
        // ✅ Generate unique tracking code if not provided
        $trackingCode = $request->tracking_code ?? 'TRK-' . strtoupper(substr(uniqid(), -8));
        
        // ✅ Parcel create
        $parcel = Parcel::create([
            'tracking_code' => $trackingCode,
            'merchant_id' => $merchant_id,
            'assigned_to' => $assigned_to,
            'pickup_location' => $request->pickup_location,
            'pickup_city' => $request->pickup_city,
            'dropoff_location' => $request->dropoff_location,
            'dropoff_city' => $request->dropoff_city,
            'parcel_status' => $request->parcel_status ?? 'pending',
            'payment_method' => $request->payment_method,
            'rider_payout' => $rider_payout,
            'company_payout' => $company_payout,
        ]);

        // ✅ Geocode pickup location and cache in DB
        try {
            $geocoder = new \App\Services\GeocodingService();
            $pickupCoords = $geocoder->geocodeAddress($request->pickup_location, $request->pickup_city);
            if ($pickupCoords) {
                $parcel->pickup_lat = $pickupCoords['latitude'];
                $parcel->pickup_lng = $pickupCoords['longitude'];
                $parcel->save();
            }
        } catch (\Exception $e) {
            \Log::warning('Geocoding failed for pickup: ' . $e->getMessage());
        }

        // ✅ Parcel details create
        ParcelDetail::create([
            'parcel_id' => $parcel->parcel_id,
            'client_name' => $request->client_name,
            'client_phone_number' => $request->client_phone_number,
            'client_address' => $request->client_address,
            'client_email' => $request->client_email,
        ]);

        // ✅ Geocode client address and cache in DB
        try {
            $geocoder = new \App\Services\GeocodingService();
            $clientCoords = $geocoder->geocodeAddress($request->client_address);
            if ($clientCoords) {
                ParcelDetail::where('parcel_id', $parcel->parcel_id)->update([
                    'client_latitude' => $clientCoords['latitude'],
                    'client_longitude' => $clientCoords['longitude']
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Geocoding failed for client: ' . $e->getMessage());
        }

        // ✅ Generate and save 4-digit code for the parcel
        $uniqueCode = ParcelCode::generateUniqueCode();
        ParcelCode::create([
            'parcel_id' => $parcel->parcel_id,
            'code' => $uniqueCode
        ]);

        DB::commit();

        // 🚀 Send request to top 3 riders (using AI logic)
        $riderRequestResult = ['success' => false, 'riders' => []];
        if (!$assigned_to) {
            try {
                $top3Service = new Top3RiderService();
                $riderRequestResult = $top3Service->sendRequestToTop3Riders($parcel->parcel_id);
                \Log::info('Top 3 riders notified', $riderRequestResult);
            } catch (\Exception $e) {
                \Log::error('Rider Request Error: ' . $e->getMessage());
            }
        }

        // ✅ Send Email verification code to client
        $emailStatus = 'not_sent';
        if ($request->client_email) {
            try {
                Mail::to($request->client_email)->send(
                    new ParcelCodeMail($uniqueCode, $request->client_name, $trackingCode)
                );
                $emailStatus = 'email_sent';
            } catch (\Exception $e) {
                $emailStatus = 'email_failed: ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => true,
            'message' => $riderRequestResult['success'] 
                ? 'Parcel created. Request sent to top 3 riders - waiting for acceptance.' 
                : 'Parcel created. No available riders found in the same city.',
            'parcel_id' => $parcel->parcel_id,
            'tracking_code' => $trackingCode,
            'assigned_to' => null,
            'assigned_rider_name' => 'Pending Rider Acceptance',
            'riders_notified' => $riderRequestResult['riders'] ?? [],
            'riders_count' => count($riderRequestResult['riders'] ?? []),
            'client_email' => $request->client_email,
            'verification_code' => $uniqueCode,
            'email_status' => $emailStatus
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

public function show($id)
{
    $parcel = Parcel::find($id);

    if (!$parcel) {
        return response()->json([
            'status' => false,
            'message' => 'Parcel not found'
        ], 404);
    }

    // Get client details
    $clientDetails = ParcelDetail::where('parcel_id', $parcel->parcel_id)->first();
    
    // Get assigned rider name
    $assignedRider = null;
    if ($parcel->assigned_to) {
        $rider = \App\Models\User::find($parcel->assigned_to);
        $assignedRider = $rider ? $rider->first_name . ' ' . $rider->last_name : null;
    }

    return response()->json([
        'status' => true,
        'data' => [
            'parcel_id' => $parcel->parcel_id,
            'tracking_code' => $parcel->tracking_code,
            'pickup_location' => $parcel->pickup_location,
            'pickup_city' => $parcel->pickup_city,
            'dropoff_location' => $parcel->dropoff_location,
            'dropoff_city' => $parcel->dropoff_city,
            'assigned_to' => $parcel->assigned_to,
            'assigned_rider_name' => $assignedRider,
            'parcel_status' => $parcel->parcel_status,
            'payment_method' => $parcel->payment_method,
            'rider_payout' => $parcel->rider_payout,
            'company_payout' => $parcel->company_payout,
            'client_name' => $clientDetails->client_name ?? null,
            'client_phone_number' => $clientDetails->client_phone_number ?? null,
            'client_address' => $clientDetails->client_address ?? null,
            'client_email' => $clientDetails->client_email ?? null,
        ]
    ]);
}

public function getParcelsByMerchant($merchant_id = null)
{
    $user = Auth::user();
    $merchantId = $merchant_id ?? $user->id;
    
    $parcels = Parcel::where('merchant_id', $merchantId)->get();
    
    return response()->json([
        'status' => true,
        'data' => $parcels
    ]);
}

public function getParcelsByRider($rider_id)
{
    $parcels = Parcel::where('assigned_to', $rider_id)->get();
    
    return response()->json([
        'status' => true,
        'data' => $parcels
    ]);
}

// ✅ GET: Parcel by tracking code
public function getByTrackingCode($trackingCode)
{
    // Trim and clean tracking code
    $trackingCode = trim($trackingCode);
    
    \Log::info('Searching for tracking code: ' . $trackingCode);
    
    $parcel = Parcel::where('tracking_code', $trackingCode)->first();
    
    if (!$parcel) {
        \Log::warning('Parcel not found. Available codes: ' . Parcel::pluck('tracking_code')->toJson());
        return response()->json([
            'status' => false,
            'error' => 'Parcel not found'
        ], 404);
    }
    
    $clientDetails = ParcelDetail::where('parcel_id', $parcel->parcel_id)->first();
    
    $assignedRider = null;
    if ($parcel->assigned_to) {
        $rider = \App\Models\User::find($parcel->assigned_to);
        $assignedRider = $rider ? $rider->first_name . ' ' . $rider->last_name : null;
    }
    
    return response()->json([
        'status' => true,
        'data' => [
            'parcel_id' => $parcel->parcel_id,
            'tracking_code' => $parcel->tracking_code,
            'pickup_location' => $parcel->pickup_location,
            'pickup_city' => $parcel->pickup_city,
            'dropoff_location' => $parcel->dropoff_location,
            'dropoff_city' => $parcel->dropoff_city,
            'parcel_status' => $parcel->parcel_status,
            'payment_method' => $parcel->payment_method,
            'assigned_rider_name' => $assignedRider,
            'client_name' => $clientDetails->client_name ?? null,
            'client_phone_number' => $clientDetails->client_phone_number ?? null,
            'client_address' => $clientDetails->client_address ?? null,
        ]
    ]);
}

// ✅ PUT: Update parcel
public function update(Request $request, $id)
{
    $parcel = Parcel::find($id);
    
    if (!$parcel) {
        return response()->json([
            'status' => false,
            'message' => 'Parcel not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'tracking_code' => 'string|unique:parcel,tracking_code,' . $id . ',parcel_id',
        'pickup_location' => 'string',
        'pickup_city' => 'string',
        'dropoff_location' => 'string',
        'dropoff_city' => 'nullable|string',
        'parcel_status' => 'in:pending,pickup_requested,picked_up,out_for_delivery,delivered,cancelled',
        'payment_method' => 'in:cod,online',
        'rider_payout' => 'nullable|numeric|min:0',
        'collected_by_rider' => 'nullable|numeric|min:0',
        'company_payout' => 'nullable|numeric|min:0',
        
        'client_name' => 'string',
        'client_phone_number' => 'string',
        'client_address' => 'string',
        'client_email' => 'nullable|email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();
    try {
        // Update parcel first
        $updateData = $request->only([
            'tracking_code', 'pickup_location', 'pickup_city', 'dropoff_location', 'dropoff_city',
            'parcel_status', 'payment_method', 'rider_payout', 'collected_by_rider', 'company_payout'
        ]);
        
        // Handle assigned_to: null, 'N/A', or rider ID
        if ($request->has('assigned_to')) {
            $assignedTo = $request->assigned_to;
            // Convert 'N/A' or empty string to null
            if ($assignedTo === 'N/A' || $assignedTo === '' || $assignedTo === null) {
                $updateData['assigned_to'] = null;
            } else if (is_numeric($assignedTo)) {
                $updateData['assigned_to'] = (int)$assignedTo;
            }
        }
        
        $parcel->update($updateData);

        // Update parcel details if provided
        if ($request->hasAny(['client_name', 'client_phone_number', 'client_address', 'client_email'])) {
            ParcelDetail::where('parcel_id', $parcel->parcel_id)
                ->update($request->only(['client_name', 'client_phone_number', 'client_address', 'client_email']));
        }

        // Commit transaction first
        DB::commit();

        // 🤖 AI Auto-Assignment - Only if explicitly requested or already null
        $parcel->refresh();
        
        // Only trigger AI if:
        // 1. Parcel was already null (not manually set to N/A in this request)
        // 2. OR if pickup_city/location changed and parcel is null
        $wasSetToNA = $request->has('assigned_to') && 
                      ($request->assigned_to === 'N/A' || $request->assigned_to === null || $request->assigned_to === '');
        
        // If user explicitly set to N/A, don't auto-assign (let them use retry button)
        // If parcel was already null and city/location changed, try AI
        if (!$parcel->assigned_to && !$wasSetToNA && 
            ($request->has('pickup_city') || $request->has('pickup_location'))) {
            \Log::info('Triggering AI assignment for parcel: ' . $parcel->parcel_id);
            $aiService = new \App\Services\AIOnlyRiderAssignmentService();
            $aiService->assignParcels();
            $parcel->refresh();
        }

        // Get assigned rider name for response
        $assignedRiderName = 'N/A';
        if ($parcel->assigned_to) {
            $rider = \App\Models\User::find($parcel->assigned_to);
            $assignedRiderName = $rider ? $rider->first_name . ' ' . $rider->last_name : 'N/A';
        }

        return response()->json([
            'status' => true,
            'message' => $parcel->assigned_to ? 'Parcel updated and assigned by AI successfully' : 'Parcel updated. No available rider found - remains N/A.',
            'assigned_rider_name' => $assignedRiderName,
            'ai_assignment' => $parcel->assigned_to ? 'success' : 'no_rider_available',
            'data' => $parcel->fresh()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Failed to update parcel: ' . $e->getMessage()
        ], 500);
    }
}

// ✅ DELETE: Delete parcel
public function destroy($id)
{
    $parcel = Parcel::find($id);
    
    if (!$parcel) {
        return response()->json([
            'status' => false,
            'message' => 'Parcel not found'
        ], 404);
    }

    DB::beginTransaction();
    try {
        // Delete parcel details first
        ParcelDetail::where('parcel_id', $parcel->parcel_id)->delete();
        
        // Delete parcel
        $parcel->delete();

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Parcel deleted successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete parcel: ' . $e->getMessage()
        ], 500);
    }
}

// ✅ POST: Verify delivery with 4-digit code
public function verifyDelivery(Request $request)
{
    $validator = Validator::make($request->all(), [
        'tracking_code' => 'required|string',
        'verification_code' => 'required|digits:4'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $parcel = Parcel::where('tracking_code', $request->tracking_code)->first();

    if (!$parcel) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid tracking code'
        ], 404);
    }

    $parcelCode = ParcelCode::where('parcel_id', $parcel->parcel_id)->first();

    if (!$parcelCode || $parcelCode->code !== $request->verification_code) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid verification code'
        ], 400);
    }

    $parcel->parcel_status = 'delivered';
     $parcel->tracking_active = 0;
    $parcel->save();

    return response()->json([
        'status' => true,
        'message' => 'Parcel marked as delivered successfully!',
        'parcel_id' => $parcel->parcel_id,
        'tracking_code' => $parcel->tracking_code
    ]);
}

private function extractCityFromLocation($location)
{
    $parts = array_map('trim', explode(',', $location));
    return end($parts);
}

public function getMerchantParcels($merchantId)
{
    try {
        $parcels = DB::table('parcel')
            ->where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($parcels);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to fetch parcels'], 500);
    }
}

public function requestDelivery(Request $request)
{
    $validator = Validator::make($request->all(), [
        'merchant_id' => 'required|integer',
        'merchant_name' => 'required|string',
        'pickup_location' => 'required|string',
        'pickup_city' => 'required|string',
        'dropoff_location' => 'required|string',
        'dropoff_city' => 'required|string',
        'payment_method' => 'required|in:cod,online',
        'client_name' => 'required|string',
        'client_phone' => 'required|string',
        'client_address' => 'required|string',
        'client_email' => 'nullable|email',
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    }

    DB::beginTransaction();
    try {
        $trackingCode = 'TRK-' . strtoupper(substr(uniqid(), -8));
        
        $parcel = Parcel::create([
            'tracking_code' => $trackingCode,
            'merchant_id' => $request->merchant_id,
            'pickup_location' => $request->pickup_location,
            'pickup_city' => $request->pickup_city,
            'dropoff_location' => $request->dropoff_location,
            'dropoff_city' => $request->dropoff_city,
            'parcel_status' => 'pending',
            'payment_method' => $request->payment_method,
        ]);

        ParcelDetail::create([
            'parcel_id' => $parcel->parcel_id,
            'client_name' => $request->client_name,
            'client_phone_number' => $request->client_phone,
            'client_address' => $request->client_address,
            'client_email' => $request->client_email,
        ]);

        $uniqueCode = ParcelCode::generateUniqueCode();
        ParcelCode::create([
            'parcel_id' => $parcel->parcel_id,
            'code' => $uniqueCode
        ]);

        DB::commit();

        // Send email to admin
        $deliveryData = [
            'tracking_code' => $trackingCode,
            'merchant_name' => $request->merchant_name,
            'pickup_location' => $request->pickup_location,
            'pickup_city' => $request->pickup_city,
            'dropoff_location' => $request->dropoff_location,
            'dropoff_city' => $request->dropoff_city,
            'payment_method' => $request->payment_method,
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'client_address' => $request->client_address,
            'client_email' => $request->client_email,
        ];

        try {
            Mail::to(env('ADMIN_EMAIL', 'admin@courierhub.com'))->send(new DeliveryRequestMail($deliveryData));
        } catch (\Exception $e) {
            \Log::error('Failed to send delivery request email: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'Delivery request submitted successfully',
            'tracking_code' => $trackingCode,
            'verification_code' => $uniqueCode
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
    }
}

}