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

class ParcelController extends Controller
{
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
        'assigned_to' => 'nullable|integer|exists:users,id',
        'pickup_location' => 'required|string',
        'pickup_city' => 'required|string',
        'dropoff_location' => 'required|string',
        'dropoff_city' => 'nullable|string',
        'parcel_status' => 'nullable|in:pending,in_transit,delivered,cancelled,picked_up,out_for_delivery',
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

    // Get merchant_id from selected company
    $merchant_id = null;
    if ($request->company_id) {
        // Find the user who owns this company
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

        // ✅ Parcel details create
        ParcelDetail::create([
            'parcel_id' => $parcel->parcel_id,
            'client_name' => $request->client_name,
            'client_phone_number' => $request->client_phone_number,
            'client_address' => $request->client_address,
            'client_email' => $request->client_email,
        ]);

        // ✅ Generate and save 4-digit code for the parcel
        $uniqueCode = ParcelCode::generateUniqueCode();
        ParcelCode::create([
            'parcel_id' => $parcel->parcel_id,
            'code' => $uniqueCode
        ]);

        // Commit transaction first so Python script can see the parcel
        DB::commit();

        // 🤖 AI Auto-Assignment after parcel creation
        // \Log::info('Checking AI assignment for parcel: ' . $parcel->parcel_id . ', assigned_to: ' . ($assigned_to ?? 'null'));
        
        // if (!$assigned_to) {
        //     \Log::info('Calling AI service for parcel: ' . $parcel->parcel_id);
        //     $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        //     $aiResult = $aiService->assignParcels();
        //     \Log::info('AI Result: ' . json_encode($aiResult));
        //     
        //     // Refresh parcel to get updated assigned_to
        //     $parcel->refresh();
        //     $assigned_to = $parcel->assigned_to;
        //     \Log::info('After refresh, assigned_to: ' . ($assigned_to ?? 'null'));
        // }

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

        // Get assigned rider name
        $assignedRiderName = 'N/A';
        if ($assigned_to) {
            $rider = \App\Models\User::find($assigned_to);
            $assignedRiderName = $rider ? $rider->first_name . ' ' . $rider->last_name : 'N/A';
        }

        return response()->json([
            'status' => true,
            'message' => $assigned_to ? 'Parcel created and assigned by AI successfully.' : 'Parcel created. No available rider found - marked as N/A.',
            'parcel_id' => $parcel->parcel_id,
            'tracking_code' => $trackingCode,
            'assigned_to' => $assigned_to,
            'assigned_rider_name' => $assignedRiderName,
            'ai_assignment' => $assigned_to ? 'success' : 'no_rider_available',
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
        'assigned_to' => 'nullable|string',  // Allow null or 'N/A'
        'pickup_location' => 'string',
        'pickup_city' => 'string',
        'dropoff_location' => 'string',
        'dropoff_city' => 'nullable|string',
        'parcel_status' => 'in:pending,in_transit,delivered,cancelled,picked_up,out_for_delivery',
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

}