<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MerchantCompany;
use App\Models\Address;
use Illuminate\Support\Facades\Hash;

class MerchantRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'full_address' => 'required|string',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'avg_parcels_per_day' => 'nullable|integer|min:0',
            'business_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Create user first
        $user = User::create([
            'first_name' => $request->owner_name,
            'last_name' => '',
            'email' => $request->email,
            'phone' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'merchant',
        ]);

        // Create merchant company
        $company = MerchantCompany::create([
            'company_name' => $request->business_name,
            'address' => $request->full_address,
            'per_parcel_rate' => $request->avg_parcels_per_day ?? 0,
            'product_type' => $request->product_type,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'avg_parcels_per_day' => $request->avg_parcels_per_day,
        ]);

        // Update user with company_id
        $user->company_id = $company->id;
        $user->per_parcel_payout = 0;

        // Create address
        $address = Address::create([
            'user_id' => $user->id,
            'city' => $request->city,
        ]);

        $user->address_id = $address->id;
        $user->save();

        // Handle business document upload
        if ($request->hasFile('business_document')) {
            $path = $request->file('business_document')->store('merchants/documents', 'public');
            $company->business_document = $path;
            $company->save();
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Merchant registered successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->first_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'company' => [
                'name' => $company->company_name,
            ],
            'token' => $token
        ], 201);
    }
}
