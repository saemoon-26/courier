<?php

use App\Http\Controllers\API\ParcelController;
use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\RiderController;
use App\Http\Controllers\API\RiderRegistrationController;
use App\Http\Controllers\API\RiderAuthController;
use App\Http\Controllers\API\MerchantRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminDashboardController;

// Debug route
require __DIR__.'/debug.php';

// Rider Registration routes
Route::post('/rider-registrations', [RiderRegistrationController::class, 'store']);
Route::get('/rider-registrations', [RiderRegistrationController::class, 'index']);
Route::post('/rider-registrations/{id}/approve', [RiderRegistrationController::class, 'approve']);
Route::post('/rider-registrations/{id}/reject', [RiderRegistrationController::class, 'reject']);
Route::post('/rider-registrations/check-status', [RiderRegistrationController::class, 'checkStatus']);
Route::get('/rider-registrations/{id}/document/{type}', [RiderRegistrationController::class, 'getDocument']);

// Rider Authentication
Route::post('/rider/signup', [RiderAuthController::class, 'signup']);
Route::post('/rider/login', [RiderAuthController::class, 'login']);
Route::post('/rider/check-status', [RiderRegistrationController::class, 'checkStatus']);

// Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/merchant-registrations', [MerchantRegistrationController::class, 'store']);
Route::post('/merchant/check-status', function(Request $request) {
    $request->validate(['email' => 'required|email']);
    
    $user = App\Models\User::where('email', $request->email)->where('role', 'merchant')->first();
    
    if (!$user) {
        return response()->json(['status' => false, 'message' => 'Merchant not found'], 404);
    }
    
    $company = $user->company_id ? App\Models\MerchantCompany::find($user->company_id) : null;
    
    return response()->json([
        'status' => true,
        'data' => [
            'email' => $user->email,
            'name' => $user->first_name . ' ' . $user->last_name,
            'approval_status' => $company ? $company->approval_status : 'pending',
            'is_active' => $company ? $company->is_active : 0,
        ]
    ]);
});
Route::post('/login', [AuthController::class, 'login']);
Route::get('/user/{id}', [AuthController::class, 'show']);

// Parcel Management
Route::get('/parcels', [ParcelController::class, 'getAllParcels']);
Route::get('/parcels/track/{trackingCode}', [ParcelController::class, 'getByTrackingCode']);
Route::post('/parcels', [ParcelController::class, 'store']);
Route::get('/parcels/{id}', [ParcelController::class, 'show']);
Route::put('/parcels/{id}', [ParcelController::class, 'update']);
Route::delete('/parcels/{id}', [ParcelController::class, 'destroy']);
Route::post('/verify-delivery', [ParcelController::class, 'verifyDelivery']);
Route::get('/merchant/{merchantId}/parcels', [ParcelController::class, 'getMerchantParcels']);
Route::post('/merchant/request-delivery', [ParcelController::class, 'requestDelivery']);

// User Management
Route::get('/riders', [UserController::class, 'getAllRiders']);
Route::post('/riders', [UserController::class, 'createRider']);
Route::get('/riders/{id}', [UserController::class, 'getRider']);
Route::put('/riders/{id}', [UserController::class, 'updateRider']);
Route::delete('/riders/{id}', [UserController::class, 'deleteRider']);
Route::get('/riders/{id}/parcels', [RiderController::class, 'parcels']);
Route::get('/merchants', [UserController::class, 'getAllMerchants']);
Route::get('/merchants/{id}', [UserController::class, 'getMerchant']);
Route::post('/merchants', [UserController::class, 'createMerchant']);
Route::put('/merchants/{id}', [UserController::class, 'updateMerchant']);
Route::delete('/merchants/{id}', [UserController::class, 'deleteMerchant']);

// Merchant Approval Routes
Route::post('/merchants/{id}/approve', function($id) {
    $merchant = App\Models\User::find($id);
    if ($merchant && $merchant->company_id) {
        DB::table('merchant_companies')->where('id', $merchant->company_id)->update(['approval_status' => 'approved', 'is_active' => 1]);
        return response()->json(['message' => 'Merchant approved successfully']);
    }
    return response()->json(['message' => 'Merchant not found'], 404);
});

Route::post('/merchants/{id}/reject', function($id) {
    $merchant = App\Models\User::find($id);
    if ($merchant && $merchant->company_id) {
        DB::table('merchant_companies')->where('id', $merchant->company_id)->update(['approval_status' => 'rejected', 'is_active' => 0]);
        return response()->json(['message' => 'Merchant rejected successfully']);
    }
    return response()->json(['message' => 'Merchant not found'], 404);
});

// REAL AI MACHINE LEARNING RIDER ASSIGNMENT
Route::post('/auto-assign-pending', function() {
    try {
        // Call Python ML service
        $pythonScript = base_path('ai_rider_assignment.py');
        $command = "python {$pythonScript}";
        $output = shell_exec($command);
        $result = json_decode($output, true);
        
        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'ML AI assignment completed',
            'assigned' => $result['assigned'] ?? 0,
            'algorithm' => 'Real Machine Learning - Python'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'ML AI Error: ' . $e->getMessage()
        ], 500);
    }
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/merchant/parcels', [ParcelController::class, 'getParcelsByMerchant']);
    Route::get('/rider/{rider_id}/parcels', [ParcelController::class, 'getParcelsByRider']);
    Route::get('/company/{merchant_id}/parcels', [ParcelController::class, 'getParcelsByMerchant']);
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'dashboard']);
    Route::post('/address', [AddressController::class, 'store']);
    Route::get('/address/{id}', [AddressController::class, 'show']);
    Route::get('/address', [AddressController::class, 'index']);
});

// Rider Protected Routes
Route::middleware('auth:rider')->group(function () {
    Route::get('/rider/profile', [RiderAuthController::class, 'profile']);
});