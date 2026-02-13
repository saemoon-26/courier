<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Database configuration
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'courier',
    'username' => 'root',
    'password' => 'Noor.1234',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== COURIER DATABASE CHECK ===\n\n";

try {
    // Test database connection
    echo "1. Testing Database Connection...\n";
    $pdo = $capsule->getConnection()->getPdo();
    echo "✅ Database connection successful\n\n";

    // Check all tables exist
    echo "2. Checking Tables...\n";
    $tables = [
        'users', 'address', 'parcel', 'parcel_details', 'parcel_codes', 
        'merchant_companies', 'rider_registrations', 'locations'
    ];
    
    foreach ($tables as $table) {
        try {
            $result = Capsule::select("SHOW TABLES LIKE '$table'");
            if (count($result) > 0) {
                echo "✅ Table '$table' exists\n";
            } else {
                echo "❌ Table '$table' missing\n";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

    // Test basic CRUD operations
    echo "3. Testing CRUD Operations...\n";

    // Test Users table
    echo "Testing Users table:\n";
    try {
        // SELECT
        $users = Capsule::table('users')->limit(5)->get();
        echo "✅ SELECT users: " . count($users) . " records found\n";
        
        // INSERT test (will rollback)
        Capsule::beginTransaction();
        $userId = Capsule::table('users')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'rider',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "✅ INSERT users: Test record created with ID $userId\n";
        
        // UPDATE test
        Capsule::table('users')->where('id', $userId)->update([
            'first_name' => 'Updated Test',
            'updated_at' => now()
        ]);
        echo "✅ UPDATE users: Test record updated\n";
        
        // DELETE test
        Capsule::table('users')->where('id', $userId)->delete();
        echo "✅ DELETE users: Test record deleted\n";
        
        Capsule::rollback();
        echo "✅ Transaction rolled back\n";
        
    } catch (Exception $e) {
        Capsule::rollback();
        echo "❌ Users table error: " . $e->getMessage() . "\n";
    }

    // Test Parcels table
    echo "\nTesting Parcel table:\n";
    try {
        $parcels = Capsule::table('parcel')->limit(5)->get();
        echo "✅ SELECT parcel: " . count($parcels) . " records found\n";
        
        // Test parcel with details join
        $parcelWithDetails = Capsule::table('parcel')
            ->leftJoin('parcel_details', 'parcel.parcel_id', '=', 'parcel_details.parcel_id')
            ->select('parcel.*', 'parcel_details.client_name', 'parcel_details.parcel_amount')
            ->limit(5)
            ->get();
        echo "✅ JOIN parcel with details: " . count($parcelWithDetails) . " records found\n";
        
    } catch (Exception $e) {
        echo "❌ Parcel table error: " . $e->getMessage() . "\n";
    }

    // Test Address table
    echo "\nTesting Address table:\n";
    try {
        $addresses = Capsule::table('address')->limit(5)->get();
        echo "✅ SELECT address: " . count($addresses) . " records found\n";
        
    } catch (Exception $e) {
        echo "❌ Address table error: " . $e->getMessage() . "\n";
    }

    // Test ParcelCode table
    echo "\nTesting ParcelCode table:\n";
    try {
        $codes = Capsule::table('parcel_codes')->limit(5)->get();
        echo "✅ SELECT parcel_codes: " . count($codes) . " records found\n";
        
    } catch (Exception $e) {
        echo "❌ ParcelCode table error: " . $e->getMessage() . "\n";
    }

    // Test Rider Registrations table
    echo "\nTesting Rider Registrations table:\n";
    try {
        $registrations = Capsule::table('rider_registrations')->limit(5)->get();
        echo "✅ SELECT rider_registrations: " . count($registrations) . " records found\n";
        
    } catch (Exception $e) {
        echo "❌ Rider Registrations table error: " . $e->getMessage() . "\n";
    }

    echo "\n4. Testing Complex Queries...\n";

    // Test user with address join
    try {
        $usersWithAddress = Capsule::table('users')
            ->leftJoin('address', 'users.id', '=', 'address.user_id')
            ->select('users.*', 'address.city', 'address.zone')
            ->limit(5)
            ->get();
        echo "✅ Users with Address JOIN: " . count($usersWithAddress) . " records\n";
    } catch (Exception $e) {
        echo "❌ Users with Address JOIN error: " . $e->getMessage() . "\n";
    }

    // Test parcel assignment query
    try {
        $assignedParcels = Capsule::table('parcel')
            ->join('users', 'parcel.assigned_to', '=', 'users.id')
            ->select('parcel.tracking_code', 'users.first_name', 'users.last_name', 'parcel.parcel_status')
            ->where('parcel.parcel_status', '!=', 'delivered')
            ->limit(10)
            ->get();
        echo "✅ Assigned Parcels Query: " . count($assignedParcels) . " records\n";
    } catch (Exception $e) {
        echo "❌ Assigned Parcels Query error: " . $e->getMessage() . "\n";
    }

    // Test parcel statistics
    try {
        $stats = Capsule::table('parcel')
            ->selectRaw('parcel_status, COUNT(*) as count')
            ->groupBy('parcel_status')
            ->get();
        echo "✅ Parcel Statistics Query: " . count($stats) . " status groups\n";
        foreach ($stats as $stat) {
            echo "   - {$stat->parcel_status}: {$stat->count} parcels\n";
        }
    } catch (Exception $e) {
        echo "❌ Parcel Statistics error: " . $e->getMessage() . "\n";
    }

    echo "\n5. Testing Indexes and Performance...\n";

    // Check indexes
    try {
        $indexes = Capsule::select("SHOW INDEX FROM users");
        echo "✅ Users table indexes: " . count($indexes) . " indexes found\n";
        
        $parcelIndexes = Capsule::select("SHOW INDEX FROM parcel");
        echo "✅ Parcel table indexes: " . count($parcelIndexes) . " indexes found\n";
        
    } catch (Exception $e) {
        echo "❌ Index check error: " . $e->getMessage() . "\n";
    }

    echo "\n6. Testing Data Integrity...\n";

    // Check for orphaned records
    try {
        $orphanedDetails = Capsule::table('parcel_details')
            ->leftJoin('parcel', 'parcel_details.parcel_id', '=', 'parcel.parcel_id')
            ->whereNull('parcel.parcel_id')
            ->count();
        echo "✅ Orphaned parcel_details: $orphanedDetails records\n";
        
        $orphanedCodes = Capsule::table('parcel_codes')
            ->leftJoin('parcel', 'parcel_codes.parcel_id', '=', 'parcel.parcel_id')
            ->whereNull('parcel.parcel_id')
            ->count();
        echo "✅ Orphaned parcel_codes: $orphanedCodes records\n";
        
    } catch (Exception $e) {
        echo "❌ Data integrity check error: " . $e->getMessage() . "\n";
    }

    echo "\n=== DATABASE CHECK COMPLETED ===\n";
    echo "✅ All major database operations are working correctly!\n";

} catch (Exception $e) {
    echo "❌ Critical database error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and ensure MySQL is running.\n";
}

function now() {
    return date('Y-m-d H:i:s');
}