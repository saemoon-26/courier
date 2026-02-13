<?php

use App\Http\Controllers\API\ParcelController;
use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\RiderController;
use App\Http\Controllers\API\TrackingController;
use App\Http\Controllers\API\RiderDashboardController;
use App\Http\Controllers\API\RiderRegistrationController;
use App\Http\Controllers\API\RiderAuthController;
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

// Real-time Tracking
Route::post('/rider/location/update', [TrackingController::class, 'updateRiderLocation']);
Route::post('/riders/location/update', [TrackingController::class, 'updateRiderLocation']);
Route::post('/riders/tracking/start', [TrackingController::class, 'startTracking']);
Route::post('/riders/tracking/stop', [TrackingController::class, 'stopTracking']);
Route::get('/parcel/{parcelId}/rider-location', [TrackingController::class, 'getRiderLocation']);
Route::get('/track/{trackingCode}', [TrackingController::class, 'trackByCode']);
Route::get('/track/live/{trackingCode}', [TrackingController::class, 'trackByCode']);

// Test geocoding
Route::get('/test-geocode', function() {
    $address = request('address', 'medina town faisalabad');
    $service = new \App\Services\GeocodingService();
    $result = $service->geocodeAddress($address);
    return response()->json([
        'address' => $address,
        'result' => $result,
        'status' => 'working'
    ]);
});

// Test simple endpoint
Route::get('/test', function() {
    return response()->json(['status' => 'API Working', 'time' => now()]);
});

// Geocode address endpoint
Route::post('/geocode-address', function(\Illuminate\Http\Request $request) {
    $address = $request->input('address');
    if (!$address) {
        return response()->json(['success' => false, 'message' => 'Address required'], 400);
    }
    
    $service = new \App\Services\GeocodingService();
    $result = $service->geocodeAddress($address);
    
    if ($result) {
        return response()->json([
            'success' => true,
            'coordinates' => [
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude']
            ],
            'display_name' => $result['display_name']
        ]);
    }
    
    return response()->json(['success' => false, 'message' => 'Unable to geocode address'], 404);
});

// Rider Dashboard - View delivery route and client location
Route::get('/rider/{riderId}/assigned-parcels', [RiderDashboardController::class, 'getAssignedParcels']);
Route::post('/rider/parcel/{parcelId}/start-tracking', [RiderDashboardController::class, 'startTracking']);
Route::post('/rider/parcel/{parcelId}/stop-tracking', [RiderDashboardController::class, 'stopTracking']);

// User Management
Route::get('/riders', [UserController::class, 'getAllRiders']);
Route::post('/riders', [UserController::class, 'createRider']);
Route::get('/riders/{id}', [UserController::class, 'getRider']);
Route::put('/riders/{id}', [UserController::class, 'updateRider']);
Route::delete('/riders/{id}', [UserController::class, 'deleteRider']);
Route::get('/riders/{id}/parcels', [RiderController::class, 'parcels']);
Route::get('/merchants', [UserController::class, 'getAllMerchants']);
Route::post('/merchants', [UserController::class, 'createMerchant']);
Route::put('/merchants/{id}', [UserController::class, 'updateMerchant']);
Route::delete('/merchants/{id}', [UserController::class, 'deleteMerchant']);

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