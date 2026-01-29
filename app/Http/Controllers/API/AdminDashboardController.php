<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Parcel;
use App\Models\MerchantCompany;
use DB;

class AdminDashboardController extends Controller
{
    public function dashboard()
    {
        // 1. Counts
        $totalRiders = User::where('role', 'rider')->count();
        $totalMerchants = User::where('role', 'merchant')->count();

        $totalPending = Parcel::where('parcel_status', 'pending')->count();
        $totalDelivered = Parcel::where('parcel_status', 'delivered')->count();
        $totalInTransit = Parcel::where('parcel_status', 'in_transit')->count();
        $totalCancelled = Parcel::where('parcel_status', 'cancelled')->count();

        // 2. Amounts received from companies
        $companies = MerchantCompany::select('id', 'company_name', 'per_parcel_rate')->get();

     $companySummary = $companies->map(function ($company) {
            $parcels = Parcel::where('merchant_id', $company->id)->get();
            $parcelCount = $parcels->count();
            $totalAmount = $parcels->sum('company_payout');

            return [
                'company_name' => $company->company_name,
                'amount' => number_format($totalAmount, 2),
                'parcels' => $parcelCount,
                'rate_per_parcel' => number_format($company->per_parcel_rate, 2),
            ];
        });

        // 3. Amounts paid to riders
        $riders = User::where('role', 'rider')
            ->select('id', 'first_name', 'last_name', 'per_parcel_payout')
            ->get();

        $riderSummary = $riders->map(function ($rider) {
            $parcels = Parcel::where('assigned_to', $rider->id)->get();
            $parcelCount = $parcels->count();
            $totalPayout = $parcels->sum('rider_payout');

            return [
                'rider_name' => $rider->first_name,
                'amount' => number_format($totalPayout, 2),
                'parcels' => $parcelCount,
                'rate_per_parcel' => number_format($rider->per_parcel_payout, 2),
            ];
        });

        // 4. Payout Summary By Rider (Cash Paid)
        $payoutByRider = $riders->map(function ($rider) {
            $deliveredParcels = Parcel::where('assigned_to', $rider->id)
                ->where('parcel_status', 'delivered')
                ->get();

            return [
                'rider_full_name' => $rider->first_name . ' ' . $rider->last_name,
                'total_parcels_delivered_by_rider' => $deliveredParcels->count(),
                'total_amount_paid_to_rider' => number_format($deliveredParcels->sum('rider_payout'), 2),
                'rate_per_parcel' => number_format($rider->per_parcel_payout, 2),
            ];
        });

        // 5. Payout Summary By Company (COD handling)
        $payoutByCompany = $companies->map(function ($company) {
            $codParcels = Parcel::where('merchant_id', $company->id)
                ->where('payment_method', 'cod')
                ->get();

            return [
                'company_name' => $company->company_name,
                'total_parcels' => $codParcels->count(),
                'total_amount_paid_to_company_for_cad_parcels' => number_format($codParcels->sum('rider_payout'), 2),
                'total_amount_paid_by_company_for_parcels' => number_format($codParcels->sum('company_payout'), 2),
            ];
        });

        // 6. Admin Profit
        $totalCompanyAmount = $companySummary->sum(fn($c) => (float) $c['amount']);
        $totalRiderAmount = $riderSummary->sum(fn($r) => (float) $r['amount']);
        $adminProfit = number_format($totalCompanyAmount - $totalRiderAmount, 2);

        return response()->json([
            'total_number_of_riders' => $totalRiders,
            'total_number_of_merchants' => $totalMerchants,
            'total_pending_parcels' => $totalPending,
            'total_delivered_parcels' => $totalDelivered,
            'total_in_transit_parcels' => $totalInTransit,
            'total_cancelled_parcels' => $totalCancelled,
            'total_amount_paid_by_companies_to_admin' => $companySummary,
            'total_amount_paid_to_riders_by_admin' => $riderSummary,
            'payout_summary_by_rider' => $payoutByRider,
            'payout_summary_by_company' => $payoutByCompany,
            'admin_profit' => $adminProfit,
        ]);
    }
}
