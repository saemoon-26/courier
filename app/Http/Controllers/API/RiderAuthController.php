<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Rider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class RiderAuthController extends Controller
{
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:riders_auth,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rider = RiderAuth::create([
                'full_name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active'
            ]);

            $token = $rider->createToken('rider-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Rider account created successfully',
                'rider' => $rider,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Account creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)
                ->where('role', 'rider')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Account is not active. Status: ' . $user->status
                ], 403);
            }

            $rider = Rider::where('user_id', $user->id)
                ->with(['vehicle', 'bank', 'user.address'])
                ->first();

            $token = $user->createToken('rider-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ],
                'rider' => $rider ? [
                    'id' => $rider->id,
                    'father_name' => $rider->father_name,
                    'mobile_primary' => $rider->mobile_primary,
                    'mobile_alternate' => $rider->mobile_alternate,
                    'cnic_number' => $rider->cnic_number,
                    'driving_license_number' => $rider->driving_license_number,
                    'vehicle' => $rider->vehicle,
                    'bank' => $rider->bank ? [
                        'bank_name' => $rider->bank->bank_name,
                        'account_title' => $rider->bank->account_title
                    ] : null,
                    'address' => $rider->user->address
                ] : null,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'rider') {
                return response()->json([
                    'status' => false,
                    'message' => 'Access denied - Invalid rider token'
                ], 403);
            }

            $rider = Rider::where('user_id', $user->id)
                ->with(['vehicle', 'bank', 'user.address'])
                ->first();

            return response()->json([
                'status' => true,
                'user' => $user,
                'rider' => $rider
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to get profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}