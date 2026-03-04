<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;
use App\Models\RiderDocument;
use App\Models\RiderVehicle;
use App\Models\RiderBank;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RiderRegistrationController extends Controller
{
    public function index()
    {
        try {
            $riders = Rider::with(['user.address', 'vehicle', 'bank', 'documents'])
                ->get()
                ->map(function($rider) {
                    return [
                        'id' => $rider->id,
                        'user_id' => $rider->user_id,
                        'full_name' => $rider->user->first_name . ' ' . $rider->user->last_name,
                        'email' => $rider->user->email,
                        'father_name' => $rider->father_name,
                        'mobile_primary' => $rider->mobile_primary,
                        'mobile_alternate' => $rider->mobile_alternate,
                        'cnic_number' => $rider->cnic_number,
                        'driving_license_number' => $rider->driving_license_number,
                        'city' => $rider->user->address->city ?? 'N/A',
                        'state' => $rider->user->address->state ?? 'N/A',
                        'zipcode' => $rider->user->address->zipcode ?? '',
                        'address' => $rider->user->address->address ?? 'N/A',
                        'status' => $rider->user->status,
                        'vehicle_type' => $rider->vehicle->vehicle_type ?? '',
                        'vehicle_brand' => $rider->vehicle->vehicle_brand ?? '',
                        'vehicle_model' => $rider->vehicle->vehicle_model ?? '',
                        'vehicle_registration' => $rider->vehicle->vehicle_registration ?? '',
                        'registration_no' => $rider->vehicle->registration_no ?? '',
                        'bank_name' => $rider->bank->bank_name ?? '',
                        'account_title' => $rider->bank->account_title ?? '',
                        'documents' => $rider->documents,
                        'created_at' => $rider->created_at,
                        'updated_at' => $rider->updated_at,
                    ];
                });
            
            return response()->json([
                'status' => true,
                'data' => $riders
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
            'email' => 'required|email|unique:users,email',
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

            // 1. Create Address
            $address = Address::create([
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'country' => 'Pakistan',
                'zipcode' => $request->zipcode ?? null
            ]);

            // 2. Create User (pending status)
            $user = User::create([
                'first_name' => explode(' ', $data['full_name'])[0],
                'last_name' => trim(str_replace(explode(' ', $data['full_name'])[0], '', $data['full_name'])),
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'rider',
                'address_id' => $address->id,
                'status' => 'pending'
            ]);

            // 3. Create Rider
            $rider = Rider::create([
                'user_id' => $user->id,
                'father_name' => $data['father_name'],
                'mobile_primary' => $data['mobile_primary'],
                'mobile_alternate' => $data['mobile_alternate'],
                'cnic_number' => $data['cnic_number'],
                'driving_license_number' => $data['driving_license_number']
            ]);

            // 4. Create Vehicle
            RiderVehicle::create([
                'rider_id' => $rider->id,
                'vehicle_type' => $data['vehicle_type'],
                'vehicle_brand' => $data['vehicle_brand'],
                'vehicle_model' => $data['vehicle_model'],
                'vehicle_registration' => $data['vehicle_registration']
            ]);

            // 5. Create Bank (if provided)
            if (!empty($data['bank_name'])) {
                RiderBank::create([
                    'rider_id' => $rider->id,
                    'bank_name' => $data['bank_name'],
                    'account_number' => $data['account_number'],
                    'account_title' => $data['account_title']
                ]);
            }

            // 6. Create Documents
            $documentMapping = [
                'profile_picture' => 'profile_picture',
                'cnic_front_image' => 'cnic_front',
                'cnic_back_image' => 'cnic_back',
                'driving_license_image' => 'driving_license',
                'vehicle_registration_book' => 'vehicle_registration_book',
                'vehicle_image' => 'vehicle_image',
                'electricity_bill' => 'electricity_bill'
            ];

            foreach ($fileFields as $field) {
                if (isset($data[$field])) {
                    RiderDocument::create([
                        'rider_id' => $rider->id,
                        'document_type' => $documentMapping[$field],
                        'document_path' => $data[$field],
                        'status' => 'pending'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Rider registration submitted successfully. Admin will review your application.',
                'data' => [
                    'rider_id' => $rider->id,
                    'user_id' => $user->id
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
        DB::beginTransaction();
        try {
            $rider = Rider::with(['user.address', 'vehicle', 'bank', 'documents'])->findOrFail($id);
            
            // Update user status to active
            $rider->user->update(['status' => 'active']);
            
            // Approve all documents
            $rider->documents()->update([
                'status' => 'approved',
                'verified_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Rider approved successfully',
                'rider' => [
                    'id' => $rider->id,
                    'user_id' => $rider->user_id,
                    'full_name' => $rider->user->first_name . ' ' . $rider->user->last_name,
                    'email' => $rider->user->email,
                    'father_name' => $rider->father_name,
                    'mobile_primary' => $rider->mobile_primary,
                    'mobile_alternate' => $rider->mobile_alternate,
                    'cnic_number' => $rider->cnic_number,
                    'driving_license_number' => $rider->driving_license_number,
                    'city' => $rider->user->address->city ?? '',
                    'state' => $rider->user->address->state ?? '',
                    'address' => $rider->user->address->address ?? '',
                    'zipcode' => $rider->user->address->zipcode ?? '',
                    'status' => $rider->user->status,
                    'vehicle' => [
                        'vehicle_type' => $rider->vehicle->vehicle_type ?? '',
                        'vehicle_brand' => $rider->vehicle->vehicle_brand ?? '',
                        'vehicle_model' => $rider->vehicle->vehicle_model ?? '',
                        'vehicle_registration' => $rider->vehicle->vehicle_registration ?? '',
                        'registration_no' => $rider->vehicle->registration_no ?? ''
                    ],
                    'bank' => [
                        'bank_name' => $rider->bank->bank_name ?? '',
                        'account_number' => $rider->bank ? '****' . substr($rider->bank->account_number, -4) : '',
                        'account_title' => $rider->bank->account_title ?? ''
                    ],
                    'documents' => $rider->documents
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
            $rider = Rider::with('user', 'documents')->findOrFail($id);
            
            // Update user status
            $rider->user->update(['status' => 'rejected']);
            
            // Reject all documents
            $rider->documents()->update([
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
            $user = User::where('email', $request->email)->where('role', 'rider')->first();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'No registration found with this email'
                ], 404);
            }

            $rider = Rider::where('user_id', $user->id)->with('documents')->first();
            $rejectedDoc = $rider->documents()->where('status', 'rejected')->first();

            $response = [
                'status' => true,
                'registration_status' => $user->status,
                'message' => $this->getStatusMessage($user->status)
            ];

            if ($user->status === 'rejected' && $rejectedDoc && $rejectedDoc->rejection_reason) {
                $response['rejection_reason'] = $rejectedDoc->rejection_reason;
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
            $document = RiderDocument::where('rider_id', $id)
                ->where('document_type', str_replace('_image', '', $type))
                ->first();
            
            if (!$document) {
                return response()->json(['error' => 'Document not found'], 404);
            }
            
            $filePath = $document->document_path;
            
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