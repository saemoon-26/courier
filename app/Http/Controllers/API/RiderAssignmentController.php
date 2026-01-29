<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use Illuminate\Http\Request;

class RiderAssignmentController extends Controller
{
    public function autoAssign(Request $request)
    {
        $request->validate([
            'parcel_id' => 'required|exists:parcel,parcel_id',
            'pickup_address' => 'required|string',
            'pickup_city' => 'required|string'
        ]);

        $parcel = Parcel::findOrFail($request->parcel_id);

        if ($parcel->assigned_to) {
            return response()->json(['success' => false, 'message' => 'Already assigned'], 400);
        }

        $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        $result = $aiService->assignBestRiderWithAI();

        return response()->json([
            'success' => $result['assigned'] > 0,
            'message' => $result['message'],
            'assigned' => $result['assigned'],
            'total_parcels' => $result['total_parcels'] ?? 0
        ]);
    }

    public function retryPendingAssignments()
    {
        $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        $result = $aiService->assignParcels();

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'assigned' => $result['assigned'],
            'total' => $result['total_parcels'] ?? 0
        ]);
    }

    public function suggestRiders(Request $request)
    {
        $request->validate([
            'pickup_address' => 'required|string',
            'pickup_city' => 'required|string'
        ]);

        $aiService = new \App\Services\AIOnlyRiderAssignmentService();
        $result = $aiService->assignParcels();

        return response()->json([
            'success' => $result['assigned'] > 0,
            'message' => $result['message'],
            'assigned' => $result['assigned']
        ]);
    }
}