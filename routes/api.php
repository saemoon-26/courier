<?php

use App\Http\Controllers\API\ParcelController;
use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\RiderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminDashboardController;

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

// User Management
Route::get('/riders', [UserController::class, 'getAllRiders']);
Route::get('/riders/{id}', [RiderController::class, 'show']);
Route::get('/riders/{id}/parcels', [RiderController::class, 'parcels']);
Route::post('/riders', [UserController::class, 'createRider']);
Route::put('/riders/{id}', [UserController::class, 'updateRider']);
Route::delete('/riders/{id}', [UserController::class, 'deleteRider']);
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