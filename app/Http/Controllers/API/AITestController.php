<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Http\Request;

class AITestController extends Controller
{
    public function testAIAssignment()
    {
        $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        
        // Test 1: Check available riders
        $availableRiders = User::where('role', 'rider')
            ->where(function($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->with(['address', 'parcels' => function($q) {
                $q->whereIn('parcel_status', ['pending', 'picked_up', 'in_transit']);
            }])
            ->get();
        
        $riderStats = [];
        foreach ($availableRiders as $rider) {
            $activeParcels = $rider->parcels->count();
            $riderStats[] = [
                'id' => $rider->id,
                'name' => $rider->first_name . ' ' . $rider->last_name,
                'city' => $rider->address->city ?? 'No City',
                'address' => $rider->address->address ?? 'No Address',
                'active_parcels' => $activeParcels,
                'is_available' => $activeParcels < 5,
                'status' => $rider->status ?? 'active'
            ];
        }
        
        // Test 2: Check pending parcels
        $pendingParcels = Parcel::where(function($query) {
                $query->whereNull('assigned_to')->orWhere('assigned_to', 'N/A')->orWhere('assigned_to', '');
            })
            ->whereIn('parcel_status', ['pending', 'picked_up'])
            ->get();
        
        $pendingStats = [];
        foreach ($pendingParcels as $parcel) {
            $pendingStats[] = [
                'id' => $parcel->parcel_id,
                'tracking_code' => $parcel->tracking_code,
                'pickup_location' => $parcel->pickup_location,
                'pickup_city' => $parcel->pickup_city,
                'assigned_to' => $parcel->assigned_to
            ];
        }
        
        // Test 3: Test AI assignment for different cities
        $testCases = [
            ['address' => 'Partab Nagar, Faisalabad', 'city' => 'Faisalabad'],
            ['address' => 'Gulberg, Lahore', 'city' => 'Lahore'],
            ['address' => 'Gulshan-e-Iqbal, Karachi', 'city' => 'Karachi'],
            ['address' => 'F-7, Islamabad', 'city' => 'Islamabad']
        ];
        
        $aiTestResults = [];
        foreach ($testCases as $test) {
            $aiService = new \App\Services\AIOnlyRiderAssignmentService();
            $result = $aiService->assignBestRiderWithAI();
            
            $aiTestResults[] = [
                'test_address' => $test['address'],
                'test_city' => $test['city'],
                'ai_result' => $result
            ];
        }
        
        return response()->json([
            'ai_service_status' => 'Testing AI Assignment Logic',
            'total_riders' => $availableRiders->count(),
            'available_riders' => array_filter($riderStats, fn($r) => $r['is_available']),
            'busy_riders' => array_filter($riderStats, fn($r) => !$r['is_available']),
            'total_pending_parcels' => $pendingParcels->count(),
            'pending_parcels_sample' => array_slice($pendingStats, 0, 5),
            'ai_test_results' => $aiTestResults,
            'rider_limit_check' => [
                'max_parcels_per_rider' => 5,
                'riders_at_limit' => count(array_filter($riderStats, fn($r) => $r['active_parcels'] >= 5))
            ]
        ]);
    }
    
    public function testRetryAssignment()
    {
        // Simulate retry pending assignments
        $pendingParcels = Parcel::where(function($query) {
                $query->whereNull('assigned_to')->orWhere('assigned_to', 'N/A')->orWhere('assigned_to', '');
            })
            ->whereIn('parcel_status', ['pending', 'picked_up'])
            ->limit(5) // Test with first 5 only
            ->get();
        
        if ($pendingParcels->isEmpty()) {
            return response()->json([
                'test_status' => 'No pending parcels to test',
                'message' => 'All parcels are already assigned'
            ]);
        }
        
        $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        $testResults = [];
        
        foreach ($pendingParcels as $parcel) {
            $beforeAssignment = [
                'parcel_id' => $parcel->parcel_id,
                'tracking_code' => $parcel->tracking_code,
                'pickup_location' => $parcel->pickup_location,
                'pickup_city' => $parcel->pickup_city,
                'assigned_to_before' => $parcel->assigned_to
            ];
            
            // Test AI assignment
            $result = $aiService->assignBestRiderWithAI();
            
            $testResults[] = [
                'before' => $beforeAssignment,
                'ai_result' => $result,
                'test_status' => $result['assigned'] > 0 ? 'AI_ASSIGNED' : 'NO_ASSIGNMENT'
            ];
        }
        
        return response()->json([
            'test_type' => 'Retry Pending Assignments Test',
            'tested_parcels' => $pendingParcels->count(),
            'test_results' => $testResults,
            'summary' => [
                'successful_assignments' => count(array_filter($testResults, fn($r) => $r['test_status'] === 'AI_ASSIGNED')),
                'failed_assignments' => count(array_filter($testResults, fn($r) => $r['test_status'] === 'NO_ASSIGNMENT'))
            ]
        ]);
    }
    
    public function checkRiderLimits()
    {
        $riders = User::where('role', 'rider')
            ->with(['parcels' => function($q) {
                $q->whereIn('parcel_status', ['pending', 'picked_up', 'in_transit']);
            }])
            ->get();
        
        $riderLimitCheck = [];
        foreach ($riders as $rider) {
            $activeParcels = $rider->parcels->count();
            
            $riderLimitCheck[] = [
                'rider_id' => $rider->id,
                'rider_name' => $rider->first_name . ' ' . $rider->last_name,
                'active_parcels' => $activeParcels,
                'limit_status' => $activeParcels >= 5 ? 'AT_LIMIT' : 'AVAILABLE',
                'remaining_capacity' => max(0, 5 - $activeParcels)
            ];
        }
        
        return response()->json([
            'rider_limit_check' => 'Checking 5 parcel limit per rider',
            'total_riders' => count($riderLimitCheck),
            'available_riders' => count(array_filter($riderLimitCheck, fn($r) => $r['limit_status'] === 'AVAILABLE')),
            'riders_at_limit' => count(array_filter($riderLimitCheck, fn($r) => $r['limit_status'] === 'AT_LIMIT')),
            'detailed_status' => $riderLimitCheck
        ]);
    }
}