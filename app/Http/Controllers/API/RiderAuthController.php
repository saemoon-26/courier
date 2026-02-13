<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiderAuth;
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
            $rider = RiderAuth::where('email', $request->email)->first();

            if (!$rider || !Hash::check($request->password, $rider->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if ($rider->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Account is not active'
                ], 403);
            }

            $token = $rider->createToken('rider-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'rider' => $rider,
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
            $rider = $request->user();
            
            if (!$rider instanceof RiderAuth) {
                return response()->json([
                    'status' => false,
                    'message' => 'Access denied - Invalid rider token'
                ], 403);
            }

            return response()->json([
                'status' => true,
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