<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Top3RiderService;
use Illuminate\Http\Request;

class RiderRequestController extends Controller
{
    protected $riderService;

    public function __construct(Top3RiderService $riderService)
    {
        $this->riderService = $riderService;
    }

    public function getPendingRequests($riderId)
    {
        $requests = $this->riderService->getPendingRequestsForRider($riderId);
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function acceptRequest(Request $request)
    {
        $request->validate([
            'request_id' => 'required|integer',
            'rider_id' => 'required|integer'
        ]);

        $result = $this->riderService->acceptRequest(
            $request->request_id,
            $request->rider_id
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function rejectRequest(Request $request)
    {
        $request->validate([
            'request_id' => 'required|integer',
            'rider_id' => 'required|integer'
        ]);

        $result = $this->riderService->rejectRequest(
            $request->request_id,
            $request->rider_id
        );

        return response()->json($result);
    }
}
