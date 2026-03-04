<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Location;
use App\Models\Address;
use App\Models\MerchantCompany;

use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ✅ User Registration
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:admin,rider,merchant',
            'phone' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'address' => 'required|string',
            'country' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipcode' => 'required|string|max:20',
            'company_name' => 'required_if:role,merchant|string|max:255',
            'per_parcel_rate' => 'required_if:role,merchant|numeric|min:0',
            'per_parcel_payout' => 'required_if:role,merchant|numeric|min:0',
        ]);

        $location = Location::firstOrCreate([
            'city' => ucfirst(strtolower($request->city))
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
        ]);

        // ✅ Assign merchant company if role is merchant
        if ($request->role === 'merchant' && $request->has('company_name')) {
            $company = MerchantCompany::create([
                'company_name' => $request->company_name,
                'per_parcel_rate' => $request->per_parcel_rate ?? 0,
                'address' => $request->address ?? null
            ]);
            
            $user->company_id = $company->id;
            $user->per_parcel_payout = $request->per_parcel_payout ?? 0;
            $user->save();
        }

        $address = Address::create([
            'user_id' => $user->id,
            'city' => $request->city,
            'address' => $request->address,
            'country' => $request->country,
            'state' => $request->state,
            'zipcode' => $request->zipcode,
        ]);

        $user->address_id = $address->id;
        $user->save();

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'address_details' => [
                'country' => $address->country,
                'state' => $address->state,
                'city' => $address->city,
                'address' => $address->address,
                'zipcode' => $address->zipcode,
            ],
            'company_info' => $user->company ? [
                'company_name' => $user->company->company_name,
                'per_parcel_rate' => $user->company->per_parcel_rate
            ] : null,
            'token' => $token
        ]);
    }

    // ✅ User Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with(['address', 'company'])->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if merchant is approved
        if ($user->role === 'merchant' && $user->company) {
            if ($user->company->approval_status !== 'approved') {
                return response()->json([
                    'message' => 'Your account is ' . $user->company->approval_status . '. Please wait for admin approval.',
                    'approval_status' => $user->company->approval_status
                ], 403);
            }
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'address_details' => [
                'country' => optional($user->address)->country,
                'state' => optional($user->address)->state,
                'city' => optional($user->address)->city,
                'address' => optional($user->address)->address,
                'zipcode' => optional($user->address)->zipcode,
            ],
            'company_info' => $user->company ? [
                'company_name' => $user->company->company_name,
                'per_parcel_rate' => $user->company->per_parcel_rate,
                'approval_status' => $user->company->approval_status
            ] : null,
            'token' => $token
        ]);
    }

    // ✅ Show User Details by ID
    public function show($id)
    {
        $user = User::with(['address', 'company'])->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'address_details' => [
                'country' => optional($user->address)->country,
                'state' => optional($user->address)->state,
                'city' => optional($user->address)->city,
                'address' => optional($user->address)->address,
                'zipcode' => optional($user->address)->zipcode,
            ],
            'company_info' => $user->company ? [
                'company_name' => $user->company->company_name,
                'per_parcel_rate' => $user->company->per_parcel_rate
            ] : null,
        ]);
    }




}
