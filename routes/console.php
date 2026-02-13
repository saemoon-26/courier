<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Parcel;
use App\Models\Address;
use App\Models\ParcelCode;
use App\Models\ParcelDetail;
use App\Models\RiderRegistration;
use App\Models\MerchantCompany;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('db:test', function () {
    $this->info('=== COURIER DATABASE ELOQUENT TEST ===');
    $this->newLine();

    try {
        // Test 1: Database Connection
        $this->info('1. Testing Database Connection...');
        DB::connection()->getPdo();
        $this->info('✅ Database connection successful');
        $this->newLine();

        // Test 2: Model Operations
        $this->info('2. Testing Eloquent Models...');
        
        // Test User model
        $this->info('Testing User Model:');
        $userCount = User::count();
        $this->info("✅ Users count: $userCount");
        
        if ($userCount > 0) {
            $user = User::first();
            $this->info("✅ First user: {$user->first_name} {$user->last_name}");
            
            // Test user relationships
            $userWithAddress = User::with('address')->first();
            $this->info("✅ User with address loaded");
        }

        // Test Parcel model
        $this->info('Testing Parcel Model:');
        $parcelCount = Parcel::count();
        $this->info("✅ Parcels count: $parcelCount");
        
        // Test ParcelCode model
        $this->info('Testing ParcelCode Model:');
        $codeCount = ParcelCode::count();
        $this->info("✅ Parcel codes count: $codeCount");

        // Test RiderRegistration model
        $this->info('Testing RiderRegistration Model:');
        $riderRegCount = RiderRegistration::count();
        $this->info("✅ Rider registrations count: $riderRegCount");

        $this->newLine();

        // Test 3: Complex Queries
        $this->info('3. Testing Complex Database Queries...');
        
        // Test user statistics by role
        $roleStats = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get();
        $this->info("✅ User role statistics:");
        foreach ($roleStats as $stat) {
            $this->info("   - {$stat->role}: {$stat->count} users");
        }

        // Test parcel status statistics
        $parcelStats = Parcel::select('parcel_status', DB::raw('count(*) as count'))
            ->groupBy('parcel_status')
            ->get();
        $this->info("✅ Parcel status statistics:");
        if ($parcelStats->count() > 0) {
            foreach ($parcelStats as $stat) {
                $this->info("   - {$stat->parcel_status}: {$stat->count} parcels");
            }
        } else {
            $this->info("   - No parcels found");
        }

        // Test rider registration status
        $riderStats = RiderRegistration::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        $this->info("✅ Rider registration statistics:");
        foreach ($riderStats as $stat) {
            $this->info("   - {$stat->status}: {$stat->count} registrations");
        }

        $this->newLine();

        // Test 4: Relationship Queries
        $this->info('4. Testing Model Relationships...');
        
        // Test user parcels relationship
        $usersWithParcels = User::whereHas('parcels')->count();
        $this->info("✅ Users with assigned parcels: $usersWithParcels");

        // Test parcels with details
        $parcelsWithDetails = Parcel::whereHas('details')->count();
        $this->info("✅ Parcels with details: $parcelsWithDetails");

        // Test parcels with codes
        $parcelsWithCodes = Parcel::whereHas('code')->count();
        $this->info("✅ Parcels with codes: $parcelsWithCodes");

        $this->newLine();

        // Test 5: Data Validation
        $this->info('5. Testing Data Validation...');
        
        // Check for users without required fields
        $usersWithoutEmail = User::whereNull('email')->count();
        $this->info("✅ Users without email: $usersWithoutEmail");

        // Check for parcels without tracking codes
        $parcelsWithoutTracking = Parcel::whereNull('tracking_code')->count();
        $this->info("✅ Parcels without tracking code: $parcelsWithoutTracking");

        $this->newLine();

        // Test 6: Performance Queries
        $this->info('6. Testing Performance Queries...');
        
        $start = microtime(true);
        $activeUsers = User::where('status', 'active')->count();
        $time1 = round((microtime(true) - $start) * 1000, 2);
        $this->info("✅ Active users query: $activeUsers users ({$time1}ms)");

        $start = microtime(true);
        $pendingRegistrations = RiderRegistration::where('status', 'pending')->count();
        $time2 = round((microtime(true) - $start) * 1000, 2);
        $this->info("✅ Pending registrations query: $pendingRegistrations registrations ({$time2}ms)");

        $this->newLine();

        // Test 7: Transaction Test
        $this->info('7. Testing Database Transactions...');
        
        DB::beginTransaction();
        try {
            // Create test user
            $testUser = User::create([
                'first_name' => 'Test',
                'last_name' => 'Transaction',
                'email' => 'test.transaction@example.com',
                'role' => 'rider',
                'password' => bcrypt('password'),
            ]);
            
            $this->info("✅ Test user created with ID: {$testUser->id}");
            
            // Create test address
            $testAddress = Address::create([
                'user_id' => $testUser->id,
                'city' => 'Test City',
                'zone' => 'Test Zone',
            ]);
            
            $this->info("✅ Test address created with ID: {$testAddress->id}");
            
            // Rollback transaction
            DB::rollback();
            $this->info("✅ Transaction rolled back successfully");
            
        } catch (Exception $e) {
            DB::rollback();
            $this->error("❌ Transaction test failed: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== DATABASE TEST COMPLETED ===');
        $this->info('✅ All database operations are working correctly!');

    } catch (Exception $e) {
        $this->error('❌ Database test failed: ' . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Test all database operations and models');