<?php

namespace App\Services;

use App\Models\Parcel;
use App\Models\ParcelRiderRequest;
use App\Models\User;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Top3RiderService
{
    public function sendRequestToTop3Riders($parcelId)
    {
        $parcel = Parcel::find($parcelId);
        if (!$parcel) {
            return ['success' => false, 'message' => 'Parcel not found'];
        }

        // Get top 3 riders using AI logic
        $top3Riders = $this->getTop3RidersWithAILogic($parcel);

        if (empty($top3Riders)) {
            return ['success' => false, 'message' => 'No available riders found'];
        }

        // Create requests for top 3 riders
        foreach ($top3Riders as $riderData) {
            ParcelRiderRequest::create([
                'parcel_id' => $parcelId,
                'rider_id' => $riderData['rider_id'],
                'request_status' => 'pending',
                'rider_score' => $riderData['score'],
                'sent_at' => now()
            ]);
        }

        Log::info("Sent parcel request to top 3 riders", [
            'parcel_id' => $parcelId,
            'riders' => array_column($top3Riders, 'rider_id')
        ]);

        return [
            'success' => true,
            'message' => 'Request sent to top 3 riders',
            'riders' => $top3Riders
        ];
    }

    private function getTop3RidersWithAILogic($parcel)
    {
        // Same logic as Python AI - get riders from same city with < 5 active parcels
        $parcelCity = strtolower(trim($parcel->pickup_city ?? ''));
        
        if (empty($parcelCity)) {
            return [];
        }

        // Get available riders with their address
        $riders = DB::table('users as u')
            ->join('address as a', 'u.address_id', '=', 'a.id')
            ->leftJoin('parcel as p', function($join) {
                $join->on('u.id', '=', 'p.assigned_to')
                     ->whereIn('p.parcel_status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery']);
            })
            ->select(
                'u.id',
                'u.first_name',
                'u.last_name',
                'a.city',
                'a.latitude',
                'a.longitude',
                DB::raw('COUNT(p.parcel_id) as active_parcels')
            )
            ->where('u.role', 'rider')
            ->where('u.status', 'active')
            ->groupBy('u.id', 'u.first_name', 'u.last_name', 'a.city', 'a.latitude', 'a.longitude')
            ->having('active_parcels', '<', 5)
            ->get();

        // Filter by same city (STRICT requirement like Python AI)
        $cityRiders = $riders->filter(function($rider) use ($parcelCity) {
            return strtolower(trim($rider->city ?? '')) === $parcelCity;
        });

        if ($cityRiders->isEmpty()) {
            return [];
        }

        // Calculate scores for each rider
        $scoredRiders = [];
        foreach ($cityRiders as $rider) {
            $distanceScore = $this->calculateDistanceScore($parcel, $rider);
            
            // Same formula as Python AI: 60% distance + 20% ML placeholder + 20% workload
            $workloadScore = (5 - $rider->active_parcels) * 0.04; // 0.04 per free slot
            $mlScore = 0.5; // Placeholder (Python uses ML model)
            
            $combinedScore = ($distanceScore * 0.6) + ($mlScore * 0.2) + $workloadScore;

            $scoredRiders[] = [
                'rider_id' => $rider->id,
                'rider_name' => $rider->first_name . ' ' . $rider->last_name,
                'score' => round($combinedScore * 100, 2), // Convert to 0-100 scale
                'distance_score' => round($distanceScore, 2),
                'active_parcels' => $rider->active_parcels,
                'city' => $rider->city
            ];
        }

        // Sort by score descending and get top 3
        usort($scoredRiders, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scoredRiders, 0, 3);
    }

    private function calculateDistanceScore($parcel, $rider)
    {
        $parcelLat = $parcel->pickup_lat;
        $parcelLng = $parcel->pickup_lng;
        $riderLat = $rider->latitude;
        $riderLng = $rider->longitude;

        // If coordinates missing, return low score
        if (!$parcelLat || !$parcelLng || !$riderLat || !$riderLng) {
            return 0;
        }

        // Calculate Haversine distance (same as Python AI)
        $distance = $this->haversineDistance($parcelLat, $parcelLng, $riderLat, $riderLng);

        // Convert to score: closer = higher score (same as Python)
        if ($distance >= 10) {
            return 0;
        }

        $score = 1 - ($distance / 10);
        return max(0, $score);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        
        $a = sin($deltaLat/2) * sin($deltaLat/2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon/2) * sin($deltaLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    public function acceptRequest($requestId, $riderId)
    {
        DB::beginTransaction();
        try {
            // Lock the request row
            $request = ParcelRiderRequest::where('id', $requestId)
                ->where('rider_id', $riderId)
                ->where('request_status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$request) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Request not found or already processed'];
            }

            // Lock the parcel row
            $parcel = Parcel::where('parcel_id', $request->parcel_id)
                ->lockForUpdate()
                ->first();

            // Check if already assigned
            if ($parcel->assigned_to) {
                $request->update([
                    'request_status' => 'expired',
                    'responded_at' => now()
                ]);
                DB::commit();
                return ['success' => false, 'message' => 'Parcel already assigned to another rider'];
            }

            // Get all pending requests for this parcel with their scores
            $allRequests = ParcelRiderRequest::where('parcel_id', $request->parcel_id)
                ->where('request_status', 'pending')
                ->lockForUpdate()
                ->get();

            // Check if multiple riders accepted at same time
            $acceptingRiders = $allRequests->filter(function($req) use ($requestId) {
                return $req->id <= $requestId; // Simulate simultaneous acceptance
            });

            // If multiple, choose the one with highest score
            if ($acceptingRiders->count() > 1) {
                $bestRequest = $acceptingRiders->sortByDesc('rider_score')->first();
                
                if ($bestRequest->id !== $requestId) {
                    // This rider is not the best, reject
                    $request->update([
                        'request_status' => 'rejected',
                        'responded_at' => now()
                    ]);
                    DB::commit();
                    return ['success' => false, 'message' => 'Another rider with better score was selected'];
                }
            }

            // Assign parcel to this rider
            $parcel->update(['assigned_to' => $riderId]);

            // Mark this request as accepted
            $request->update([
                'request_status' => 'accepted',
                'responded_at' => now()
            ]);

            // Expire all other pending requests
            ParcelRiderRequest::where('parcel_id', $request->parcel_id)
                ->where('id', '!=', $requestId)
                ->where('request_status', 'pending')
                ->update([
                    'request_status' => 'expired',
                    'responded_at' => now()
                ]);

            DB::commit();

            Log::info("Rider accepted parcel", [
                'rider_id' => $riderId,
                'parcel_id' => $request->parcel_id,
                'score' => $request->rider_score
            ]);

            return [
                'success' => true,
                'message' => 'Parcel assigned successfully',
                'parcel' => $parcel
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error accepting request: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function rejectRequest($requestId, $riderId)
    {
        $request = ParcelRiderRequest::where('id', $requestId)
            ->where('rider_id', $riderId)
            ->where('request_status', 'pending')
            ->first();

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found or already processed'];
        }

        $request->update([
            'request_status' => 'rejected',
            'responded_at' => now()
        ]);

        Log::info("Rider rejected parcel", [
            'rider_id' => $riderId,
            'parcel_id' => $request->parcel_id
        ]);

        return ['success' => true, 'message' => 'Request rejected'];
    }

    public function getPendingRequestsForRider($riderId)
    {
        return ParcelRiderRequest::with(['parcel.details'])
            ->where('rider_id', $riderId)
            ->where('request_status', 'pending')
            ->orderBy('sent_at', 'desc')
            ->get()
            ->map(function($request) {
                return [
                    'request_id' => $request->id,
                    'parcel_id' => $request->parcel_id,
                    'tracking_code' => $request->parcel->tracking_code,
                    'pickup_location' => $request->parcel->pickup_location,
                    'pickup_city' => $request->parcel->pickup_city,
                    'dropoff_location' => $request->parcel->dropoff_location,
                    'client_name' => $request->parcel->details->client_name ?? 'N/A',
                    'client_phone' => $request->parcel->details->client_phone_number ?? 'N/A',
                    'payment_method' => $request->parcel->payment_method,
                    'rider_payout' => $request->parcel->rider_payout,
                    'score' => $request->rider_score,
                    'sent_at' => $request->sent_at->format('Y-m-d H:i:s')
                ];
            });
    }
}
