<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Get all riders
    public function getAllRiders()
    {
        $riders = User::where('role', 'rider')
            ->with(['address'])
            ->get();
        
        $ridersData = $riders->map(function($rider) {
            // Count ALL parcels regardless of status
            $assignedParcels = DB::table('parcel')
                ->where('assigned_to', $rider->id)
                ->whereNotNull('assigned_to')
                ->count();
            
            // Total parcels ever assigned (for reference)
            $totalParcels = DB::table('parcel')
                ->where('assigned_to', $rider->id)
                ->count();
            
            // Completed parcels today
            $completedToday = DB::table('parcel')
                ->where('assigned_to', $rider->id)
                ->where('parcel_status', 'delivered')
                ->whereDate('updated_at', today())
                ->count();
            
            return [
                'id' => $rider->id,
                'first_name' => $rider->first_name,
                'last_name' => $rider->last_name,
                'email' => $rider->email,
                'phone' => $rider->phone,
                'per_parcel_payout' => $rider->per_parcel_payout,
                'rating' => $rider->rating ?? 5.0,
                'status' => $rider->status ?? 'active',
                'assigned_parcels_count' => $assignedParcels,
                'total_parcels_assigned' => $totalParcels,
                'completed_today' => $completedToday,
                'is_available' => $assignedParcels < 5,
                'workload_status' => $assignedParcels >= 5 ? 'busy' : ($assignedParcels >= 3 ? 'moderate' : 'light'),
                'address' => $rider->address,
                'last_updated' => now()->toDateTimeString()
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $ridersData,
            'summary' => [
                'total_riders' => $ridersData->count(),
                'available_riders' => $ridersData->where('is_available', true)->count(),
                'busy_riders' => $ridersData->where('is_available', false)->count(),
                'total_active_parcels' => $ridersData->sum('assigned_parcels_count')
            ]
        ]);
    }

    // Get all merchants
    public function getAllMerchants()
    {
        $merchants = User::where('role', 'merchant')->get();
        
        $merchantsData = $merchants->map(function($merchant) {
            // Get company data
            $company = null;
            if ($merchant->company_id) {
                $company = \App\Models\MerchantCompany::find($merchant->company_id);
            }
            
            // Get address data
            $address = Address::where('user_id', $merchant->id)->first();
            
            return [
                'id' => $merchant->id,
                'first_name' => $merchant->first_name,
                'last_name' => $merchant->last_name,
                'email' => $merchant->email,
                'per_parcel_payout' => $merchant->per_parcel_payout,
                'company' => $company,
                'address' => $address
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $merchantsData
        ]);
    }

    // Create rider
    public function createRider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'per_parcel_payout' => 'required|numeric|min:0',
            'address.city' => 'required|string|max:100',
            'address.address' => 'required|string',
            'address.country' => 'required|string|max:100',
            'address.state' => 'required|string|max:100',
            'address.zipcode' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->createUserWithAddress($request, 'rider');
    }

    // Create merchant
    public function createMerchant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'company_name' => 'required|string|max:255',
            'per_parcel_rate' => 'required|numeric|min:0',
            'per_parcel_payout' => 'nullable|numeric|min:0',
            'city' => 'required|string|max:100',
            'address' => 'required|string',
            'country' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipcode' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $company = \App\Models\MerchantCompany::create([
                'company_name' => $request->company_name,
                'per_parcel_rate' => $request->per_parcel_rate,
                'address' => $request->address
            ]);

            $merchant = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => str_replace('mailto:', '', $request->email),
                'password' => Hash::make('password123'),
                'role' => 'merchant',
                'company_id' => $company->id,
                'per_parcel_payout' => $request->per_parcel_payout ?? $request->per_parcel_rate,
            ]);

            $address = Address::create([
                'user_id' => $merchant->id,
                'city' => $request->city,
                'address' => $request->address,
                'country' => $request->country,
                'state' => $request->state,
                'zipcode' => $request->zipcode,
            ]);

            $merchant->address_id = $address->id;
            $merchant->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Merchant created successfully',
                'data' => [
                    'id' => $merchant->id,
                    'first_name' => $merchant->first_name,
                    'last_name' => $merchant->last_name,
                    'email' => $merchant->email,
                    'role' => $merchant->role,
                    'per_parcel_payout' => $merchant->per_parcel_payout,
                    'company_info' => [
                        'company_name' => $company->company_name,
                        'per_parcel_rate' => $company->per_parcel_rate
                    ],
                    'address' => $address
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create merchant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private method to create user with address
    private function createUserWithAddress($request, $role)
    {
        DB::beginTransaction();
        try {
            $userData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => str_replace('mailto:', '', $request->email),
                'password' => Hash::make('password123'),
                'role' => $role,
            ];

            if ($role === 'rider') {
                $userData['per_parcel_payout'] = $request->per_parcel_payout;
            } elseif ($role === 'merchant') {
                $company = \App\Models\MerchantCompany::create([
                    'company_name' => $request->company_name,
                    'per_parcel_rate' => $request->per_parcel_rate,
                    'address' => $request->address['address']
                ]);
                $userData['company_id'] = $company->id;
                $userData['per_parcel_payout'] = $request->per_parcel_rate;
            }

            $user = User::create($userData);

            $address = Address::create([
                'user_id' => $user->id,
                'city' => $request->address['city'],
                'address' => $request->address['address'],
                'country' => $request->address['country'],
                'state' => $request->address['state'],
                'zipcode' => $request->address['zipcode'],
            ]);

            $user->address_id = $address->id;
            $user->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => ucfirst($role) . ' created successfully',
                'data' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'per_parcel_payout' => $user->per_parcel_payout,
                    'address' => $address
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create ' . $role,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update merchant
    public function updateMerchant(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email|unique:users,email,' . $id,
            'company_name' => 'string|max:255',
            'per_parcel_rate' => 'numeric|min:0',
            'per_parcel_payout' => 'numeric|min:0',
            'city' => 'string|max:100',
            'address' => 'string',
            'country' => 'string|max:100',
            'state' => 'string|max:100',
            'zipcode' => 'string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $merchant = User::where('role', 'merchant')->findOrFail($id);
            
            // Update user fields
            $merchant->update($request->only(['first_name', 'last_name', 'email', 'per_parcel_payout']));

            // Update company if exists and data provided
            if ($merchant->company_id && ($request->has('company_name') || $request->has('per_parcel_rate'))) {
                \App\Models\MerchantCompany::where('id', $merchant->company_id)
                    ->update($request->only(['company_name', 'per_parcel_rate']));
            }

            // Update address if data provided
            if ($request->hasAny(['city', 'address', 'country', 'state', 'zipcode'])) {
                Address::where('user_id', $id)
                    ->update($request->only(['city', 'address', 'country', 'state', 'zipcode']));
            }

            // Reload merchant with fresh data
            $merchant = User::find($id);
            
            // Get company data
            $company = null;
            if ($merchant->company_id) {
                $company = \App\Models\MerchantCompany::find($merchant->company_id);
            }
            
            // Get address data
            $address = Address::where('user_id', $merchant->id)->first();

            return response()->json([
                'status' => true,
                'message' => 'Merchant updated successfully',
                'data' => [
                    'id' => $merchant->id,
                    'first_name' => $merchant->first_name,
                    'last_name' => $merchant->last_name,
                    'email' => $merchant->email,
                    'per_parcel_payout' => $merchant->per_parcel_payout,
                    'company' => $company,
                    'address' => $address
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update merchant: ' . $e->getMessage()], 500);
        }
    }

    // Update rider
    public function updateRider(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email|unique:users,email,' . $id,
            'per_parcel_payout' => 'numeric|min:0',
            'city' => 'string|max:100',
            'address' => 'string',
            'country' => 'string|max:100',
            'state' => 'string|max:100',
            'zipcode' => 'string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $rider = User::where('role', 'rider')->findOrFail($id);
            
            // Update user fields
            $rider->update($request->only(['first_name', 'last_name', 'email', 'per_parcel_payout']));

            // Update address if data provided
            if ($request->hasAny(['city', 'address', 'country', 'state', 'zipcode'])) {
                Address::where('user_id', $id)
                    ->update($request->only(['city', 'address', 'country', 'state', 'zipcode']));
            }

            // Reload rider with fresh data
            $rider = User::find($id);
            $address = Address::where('user_id', $rider->id)->first();

            return response()->json([
                'status' => true,
                'message' => 'Rider updated successfully',
                'data' => [
                    'id' => $rider->id,
                    'first_name' => $rider->first_name,
                    'last_name' => $rider->last_name,
                    'email' => $rider->email,
                    'per_parcel_payout' => $rider->per_parcel_payout,
                    'address' => $address
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to update rider: ' . $e->getMessage()], 500);
        }
    }

    // Delete merchant
    public function deleteMerchant($id)
    {
        try {
            $merchant = User::where('role', 'merchant')->findOrFail($id);
            $companyId = $merchant->company_id;
            
            // Delete user tokens first
            $merchant->tokens()->delete();
            
            // Remove foreign key reference
            $merchant->company_id = null;
            $merchant->save();
            
            // Delete address
            Address::where('user_id', $id)->delete();
            
            // Delete the user
            $merchant->delete();
            
            // Delete company last
            if ($companyId) {
                \App\Models\MerchantCompany::where('id', $companyId)->delete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Merchant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Failed to delete merchant: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete rider
    public function deleteRider($id)
    {
        try {
            $rider = User::where('role', 'rider')->findOrFail($id);
            
            // Delete address
            Address::where('user_id', $id)->delete();
            
            // Delete user tokens
            $rider->tokens()->delete();
            
            // Delete the user
            $rider->delete();

            return response()->json([
                'status' => true,
                'message' => 'Rider deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete rider: ' . $e->getMessage()
            ], 500);
        }
    }
}