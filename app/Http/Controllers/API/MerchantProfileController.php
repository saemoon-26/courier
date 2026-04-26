<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MerchantCompany;
use App\Models\Address;
use Illuminate\Support\Facades\Storage;

class MerchantProfileController extends Controller
{
    public function getProfile($merchantId)
    {
        $user = User::find($merchantId);
        
        if (!$user || $user->role !== 'merchant') {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        $company = $user->company_id ? MerchantCompany::find($user->company_id) : null;
        $address = $user->address_id ? Address::find($user->address_id) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
            ],
            'company' => $company ? [
                'company_name' => $company->company_name,
                'address' => $company->address,
                'product_type' => $company->product_type,
                'approval_status' => $company->approval_status,
                'is_active' => $company->is_active,
                'business_document' => $company->business_document ? url('storage/' . $company->business_document) : null,
            ] : null,
            'address' => $address ? [
                'city' => $address->city,
            ] : null,
        ]);
    }

    public function updateProfile(Request $request, $merchantId)
    {
        $user = User::find($merchantId);
        
        if (!$user || $user->role !== 'merchant') {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'product_type' => 'sometimes|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user->update($request->only(['first_name', 'last_name', 'phone']));

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $path = $request->file('profile_image')->store('merchants/profiles', 'public');
            $user->profile_image = $path;
            $user->save();
        }

        if ($user->company_id) {
            $company = MerchantCompany::find($user->company_id);
            if ($company) {
                $company->update($request->only(['company_name', 'address', 'product_type']));
            }
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }
}
