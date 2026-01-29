<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RiderDashboardController extends Controller
{

public function index()
{
    $riderId = Auth::id(); // or pass via $request->rider_id

    // ✅ Total parcels assigned to this rider
    $totalParcels = Parcel::where('assigned_to', $riderId)->count();

    // ✅ Total delivered parcels
    $deliveredParcels = Parcel::where('assigned_to', $riderId)
                              ->where('parcel_status', 'delivered')
                              ->count();

    // ✅ Total declined (cancelled) parcels
    $declinedParcels = Parcel::where('assigned_to', $riderId)
                             ->where('parcel_status', 'cancelled')
                             ->count();

    return response()->json([
        'total_parcels' => $totalParcels,
        'delivered_parcels' => $deliveredParcels,
        'declined_parcels' => $declinedParcels,
    ]);
}

public function cashReport()
{
    $riderId = Auth::id();

    $totalDeliveredCash = DB::table('parcels as p')
        ->join('parcel_details as pd', 'p.parcel_id', '=', 'pd.parcel_id')
        ->where('p.assigned_to', $riderId)
        ->where('p.payment_method', 'cod')
        ->where('p.parcel_status', 'delivered')
        ->sum('pd.parcel_amount');

    return response()->json([
        'rider_cod_cash' => $totalDeliveredCash
    ]);
}

}