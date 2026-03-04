<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            // Get rider data from new normalized structure
            $riderData = \App\Models\Rider::where('user_id', $rider->id)
                ->with(['vehicle', 'bank'])
                ->first();
            
            // Debug for rider ID 42
            if ($rider->id == 42) {
                \Log::info('Debug Rider 42:', [
                    'rider_id' => $rider->id,
                    'name' => $rider->first_name . ' ' . $rider->last_name
                ]);
            }
            
            // Real-time count from database - always accurate
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
                'profile_image' => $rider->profile_image ? asset('storage/' . $rider->profile_image) : null,
                'id_document' => $rider->id_document ? asset('storage/' . $rider->id_document) : null,
                'license_document' => $rider->license_document ? asset('storage/' . $rider->license_document) : null,
                'assigned_parcels_count' => $assignedParcels,
                'total_parcels_assigned' => $totalParcels,
                'completed_today' => $completedToday,
                'is_available' => $assignedParcels < 5,
                'workload_status' => $assignedParcels >= 5 ? 'busy' : ($assignedParcels >= 3 ? 'moderate' : 'light'),
                'address' => $rider->address,
                // Direct fields for easy frontend access
                'father_name' => $riderData ? ($riderData->father_name ?? 'N/A') : 'N/A',
                'cnic_number' => $riderData ? ($riderData->cnic_number ?? 'N/A') : 'N/A',
                'mobile_primary' => $riderData ? ($riderData->mobile_primary ?? 'N/A') : 'N/A',
                'mobile_alternate' => $riderData ? ($riderData->mobile_alternate ?? null) : null,
                'driving_license_number' => $riderData ? ($riderData->driving_license_number ?? 'N/A') : 'N/A',
                'vehicle_type' => ($riderData && $riderData->vehicle) ? ($riderData->vehicle->vehicle_type ?? 'N/A') : 'N/A',
                'vehicle_brand' => ($riderData && $riderData->vehicle) ? ($riderData->vehicle->vehicle_brand ?? 'N/A') : 'N/A',
                'vehicle_model' => ($riderData && $riderData->vehicle) ? ($riderData->vehicle->vehicle_model ?? 'N/A') : 'N/A',
                'vehicle_registration' => ($riderData && $riderData->vehicle) ? ($riderData->vehicle->vehicle_registration ?? null) : null,
                'bank_name' => ($riderData && $riderData->bank) ? ($riderData->bank->bank_name ?? 'N/A') : 'N/A',
                'account_title' => ($riderData && $riderData->bank) ? ($riderData->bank->account_title ?? 'N/A') : 'N/A',
                'registration_data' => $riderData ? [
                    'full_name' => $rider->first_name . ' ' . $rider->last_name,
                    'father_name' => $riderData->father_name ?? 'N/A',
                    'cnic_number' => $riderData->cnic_number ?? 'N/A',
                    'mobile_primary' => $riderData->mobile_primary ?? 'N/A',
                    'vehicle_type' => $riderData->vehicle ? ($riderData->vehicle->vehicle_type ?? 'N/A') : 'N/A',
                    'vehicle_brand' => $riderData->vehicle ? ($riderData->vehicle->vehicle_brand ?? 'N/A') : 'N/A',
                    'vehicle_model' => $riderData->vehicle ? ($riderData->vehicle->vehicle_model ?? 'N/A') : 'N/A',
                    'bank_name' => $riderData->bank ? ($riderData->bank->bank_name ?? 'N/A') : 'N/A',
                    'account_title' => $riderData->bank ? ($riderData->bank->account_title ?? 'N/A') : 'N/A',
                ] : null,
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

    // Get single rider
    public function getRider($id)
    {
        try {
            $rider = User::where('role', 'rider')->findOrFail($id);
            $address = Address::where('user_id', $rider->id)->first();
            
            // Get assigned parcels count
            $assignedParcels = DB::table('parcel')
                ->where('assigned_to', $rider->id)
                ->whereIn('parcel_status', ['pending', 'picked_up', 'in_transit'])
                ->count();
            
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $rider->id,
                    'first_name' => $rider->first_name,
                    'last_name' => $rider->last_name,
                    'email' => $rider->email,
                    'phone' => $rider->phone,
                    'per_parcel_payout' => $rider->per_parcel_payout,
                    'rating' => $rider->rating ?? 5.0,
                    'status' => $rider->status ?? 'active',
                    'profile_image' => $rider->profile_image ? asset('storage/' . $rider->profile_image) : null,
                    'id_document' => $rider->id_document ? asset('storage/' . $rider->id_document) : null,
                    'license_document' => $rider->license_document ? asset('storage/' . $rider->license_document) : null,
                    'assigned_parcels_count' => $assignedParcels,
                    'address' => $address
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Rider not found'
            ], 404);
        }
    }

    // Get all merchants
    public function getAllMerchants()
    {
        $merchants = User::where('role', 'merchant')->get();
        
        $merchantsData = $merchants->map(function($merchant) {
            $company = null;
            if ($merchant->company_id) {
                $company = \App\Models\MerchantCompany::find($merchant->company_id);
            }
            
            $address = Address::where('user_id', $merchant->id)->first();
            
            return [
                'id' => $merchant->id,
                'first_name' => $merchant->first_name,
                'last_name' => $merchant->last_name,
                'email' => $merchant->email,
                'phone' => $merchant->phone,
                'per_parcel_payout' => $merchant->per_parcel_payout,
                'company' => $company,
                'address' => $address,
                'approval_status' => $company ? $company->approval_status : 'pending',
                'is_active' => $company ? $company->is_active : 0,
            ];
        });
        
        return response()->json([
            'status' => true,
            'data' => $merchantsData
        ]);
    }

    // Get single merchant
    public function getMerchant($id)
    {
        try {
            $merchant = User::where('role', 'merchant')->findOrFail($id);
            
            $company = null;
            if ($merchant->company_id) {
                $company = \App\Models\MerchantCompany::find($merchant->company_id);
            }
            
            $address = Address::where('user_id', $merchant->id)->first();
            
            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $merchant->id,
                    'first_name' => $merchant->first_name,
                    'last_name' => $merchant->last_name,
                    'email' => $merchant->email,
                    'phone' => $merchant->phone,
                    'per_parcel_payout' => $merchant->per_parcel_payout,
                    'company' => $company,
                    'address' => $address,
                    'approval_status' => $company ? $company->approval_status : 'pending',
                    'is_active' => $company ? $company->is_active : 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant not found'
            ], 404);
        }
    }

    // Create rider
    public function createRider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:6',
            'mobile_primary' => 'required|string|max:20',
            'mobile_alternate' => 'nullable|string|max:20',
            'cnic_number' => 'required|string|max:20|unique:riders,cnic_number',
            'driving_license_number' => 'required|string|max:50|unique:riders,driving_license_number',
            'vehicle_type' => 'required|string|max:50',
            'vehicle_brand' => 'required|string|max:100',
            'vehicle_model' => 'required|string|max:100',
            'vehicle_registration' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'address' => 'required|string|max:1000',
            'zipcode' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_title' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'cnic_front_image' => 'nullable|image|max:2048',
            'cnic_back_image' => 'nullable|image|max:2048',
            'driving_license_image' => 'nullable|image|max:2048',
            'vehicle_registration_book' => 'nullable|file|max:5120',
            'vehicle_image' => 'nullable|image|max:2048',
            'electricity_bill' => 'nullable|file|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle file uploads
            $fileFields = [
                'profile_picture', 'cnic_front_image', 'cnic_back_image',
                'driving_license_image', 'vehicle_registration_book', 'vehicle_image', 'electricity_bill'
            ];
            $uploadedFiles = [];
            foreach ($fileFields as $field) {
                if ($request->hasFile($field)) {
                    $uploadedFiles[$field] = $request->file($field)->store('riders/registrations', 'public');
                }
            }

            // Create Address
            $address = Address::create([
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => 'Pakistan',
                'zipcode' => $request->zipcode
            ]);

            // Create User
            $user = User::create([
                'first_name' => explode(' ', $request->full_name)[0],
                'last_name' => trim(str_replace(explode(' ', $request->full_name)[0], '', $request->full_name)),
                'email' => $request->email,
                'password' => Hash::make($request->password ?? 'password123'),
                'role' => 'rider',
                'address_id' => $address->id,
                'status' => 'active'
            ]);

            // Update address with user_id
            $address->user_id = $user->id;
            $address->save();

            // Create Rider
            $rider = \App\Models\Rider::create([
                'user_id' => $user->id,
                'father_name' => $request->father_name ?? 'N/A',
                'mobile_primary' => $request->mobile_primary,
                'mobile_alternate' => $request->mobile_alternate ?? null,
                'cnic_number' => $request->cnic_number,
                'driving_license_number' => $request->driving_license_number
            ]);

            // Create Vehicle
            \App\Models\RiderVehicle::create([
                'rider_id' => $rider->id,
                'vehicle_type' => $request->vehicle_type,
                'vehicle_brand' => $request->vehicle_brand ?? 'N/A',
                'vehicle_model' => $request->vehicle_model ?? 'N/A',
                'vehicle_registration' => $request->vehicle_registration ?? null
            ]);

            // Create Bank (if provided)
            if ($request->bank_name) {
                \App\Models\RiderBank::create([
                    'rider_id' => $rider->id,
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                    'account_title' => $request->account_title
                ]);
            }

            // Create Documents
            $documentMapping = [
                'profile_picture' => 'profile_picture',
                'cnic_front_image' => 'cnic_front',
                'cnic_back_image' => 'cnic_back',
                'driving_license_image' => 'driving_license',
                'vehicle_registration_book' => 'vehicle_registration_book',
                'vehicle_image' => 'vehicle_image',
                'electricity_bill' => 'electricity_bill'
            ];
            foreach ($uploadedFiles as $field => $path) {
                \App\Models\RiderDocument::create([
                    'rider_id' => $rider->id,
                    'document_type' => $documentMapping[$field],
                    'document_path' => $path,
                    'status' => 'approved'
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Rider registered successfully',
                'data' => [
                    'id' => $rider->id,
                    'user_id' => $user->id,
                    'email' => $user->email
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to register rider',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'per_parcel_payout' => 'nullable|numeric|min:0',
            'father_name' => 'nullable|string|max:255',
            'cnic_number' => 'nullable|string|max:20',
            'mobile_primary' => 'nullable|string|max:20',
            'mobile_alternate' => 'nullable|string|max:20',
            'driving_license_number' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'vehicle_brand' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_registration' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zipcode' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::where('role', 'rider')->findOrFail($id);
            
            // Update user fields
            $user->update($request->only(['first_name', 'last_name', 'email', 'per_parcel_payout']));

            // Update rider table
            $riderData = \App\Models\Rider::where('user_id', $id)->first();
            if ($riderData) {
                $riderData->update($request->only([
                    'father_name',
                    'cnic_number',
                    'mobile_primary',
                    'mobile_alternate',
                    'driving_license_number'
                ]));

                // Update vehicle information - check if vehicle exists first
                $vehicle = \App\Models\RiderVehicle::where('rider_id', $riderData->id)->first();
                if ($vehicle && $request->hasAny(['vehicle_type', 'vehicle_brand', 'vehicle_model', 'vehicle_registration'])) {
                    $vehicle->update($request->only([
                        'vehicle_type',
                        'vehicle_brand',
                        'vehicle_model',
                        'vehicle_registration'
                    ]));
                } elseif (!$vehicle && $request->hasAny(['vehicle_type', 'vehicle_brand', 'vehicle_model', 'vehicle_registration'])) {
                    // Create vehicle if it doesn't exist but vehicle data is provided
                    \App\Models\RiderVehicle::create([
                        'rider_id' => $riderData->id,
                        'vehicle_type' => $request->vehicle_type,
                        'vehicle_brand' => $request->vehicle_brand,
                        'vehicle_model' => $request->vehicle_model,
                        'vehicle_registration' => $request->vehicle_registration
                    ]);
                }
            }

            // Update address if data provided
            if ($request->hasAny(['city', 'address', 'country', 'state', 'zipcode'])) {
                Address::where('user_id', $id)
                    ->update($request->only(['city', 'address', 'country', 'state', 'zipcode']));
            }

            DB::commit();

            // Reload rider with fresh data
            $user = User::find($id);
            $address = Address::where('user_id', $user->id)->first();

            return response()->json([
                'status' => true,
                'message' => 'Rider updated successfully',
                'data' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'per_parcel_payout' => $user->per_parcel_payout,
                    'address' => $address
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update rider', [
                'rider_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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