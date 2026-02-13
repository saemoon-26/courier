<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiderRegistration;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\RiderAuth;

class RiderRegistrationController extends Controller
{
    public function index()
    {
        try {
            $registrations = RiderRegistration::orderBy('id', 'desc')->get();
            
            return response()->json([
                'status' => true,
                'data' => $registrations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch registrations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Log incoming request for debugging
        \Log::info('Rider Registration Request:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:rider_registrations,email|unique:riders_auth,email',
            'password' => 'required|string|min:6',
            'mobile_primary' => 'required|string|max:20',
            'mobile_alternate' => 'nullable|string|max:20',
            'cnic_number' => 'nullable|string|max:20',
            'vehicle_type' => 'required|string|max:50',
            'vehicle_brand' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_registration' => 'nullable|string|max:50',
            'driving_license_number' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'address' => 'required|string|max:1000',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->only([
                'full_name', 'father_name', 'email', 'mobile_primary', 'mobile_alternate',
                'cnic_number', 'vehicle_type', 'vehicle_brand', 'vehicle_model',
                'vehicle_registration', 'driving_license_number', 'city', 'state',
                'address', 'bank_name', 'account_number', 'account_title'
            ]);
            
            // Hash password
            $data['password'] = Hash::make($request->password);
            $data['status'] = 'pending';

            // Handle file uploads if present
            $fileFields = [
                'profile_picture', 'cnic_front_image', 'cnic_back_image',
                'driving_license_image', 'vehicle_registration_book', 'vehicle_image', 'electricity_bill'
            ];

            foreach ($fileFields as $field) {
                if ($request->hasFile($field)) {
                    $data[$field] = $request->file($field)->store('riders/registrations', 'public');
                }
            }

            // Save to rider_registrations table
            $registration = RiderRegistration::create($data);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Rider registration submitted successfully. Admin will review your application.',
                'data' => [
                    'registration_id' => $registration->id,
                    'registration' => $registration->makeHidden(['password'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        try {
            $registration = RiderRegistration::findOrFail($id);
            
            // Update status to approved
            $registration->update(['status' => 'approved']);
            
            // Create rider in riders_auth table
            $rider = RiderAuth::create([
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'password' => $registration->password, // Already hashed
                'mobile_primary' => $registration->mobile_primary,
                'city' => $registration->city,
                'state' => $registration->state,
                'vehicle_type' => $registration->vehicle_type,
                'status' => 'active'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Rider approved and account created successfully',
                'rider' => $rider
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to approve rider',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registration = RiderRegistration::findOrFail($id);
            $registration->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Rider registration rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to reject rider',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registration = RiderRegistration::where('email', $request->email)->first();
            
            if (!$registration) {
                return response()->json([
                    'status' => false,
                    'message' => 'No registration found with this email'
                ], 404);
            }

            $response = [
                'status' => true,
                'registration_status' => $registration->status,
                'message' => $this->getStatusMessage($registration->status)
            ];

            if ($registration->status === 'rejected' && $registration->rejection_reason) {
                $response['rejection_reason'] = $registration->rejection_reason;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getStatusMessage($status)
    {
        return match($status) {
            'pending' => 'Your registration is under review. Please wait for admin approval.',
            'approved' => 'Congratulations! Your registration has been approved. You can now login.',
            'rejected' => 'Sorry, your registration has been rejected. Please check the reason below.',
            default => 'Unknown status'
        };
    }

    public function getDocument($id, $type)
    {
        try {
            $registration = RiderRegistration::findOrFail($id);
            $filePath = $registration->$type;
            
            if (!$filePath) {
                return response()->json(['error' => 'Document not found'], 404);
            }
            
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (!file_exists($fullPath)) {
                return response()->json(['error' => 'File does not exist'], 404);
            }
            
            return response()->file($fullPath, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET',
                'Access-Control-Allow-Headers' => 'Content-Type'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Document access failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}