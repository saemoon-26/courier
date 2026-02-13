<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $columns = Schema::getColumnListing('rider_registrations');
    echo "Columns in rider_registrations table:\n";
    foreach ($columns as $column) {
        echo "- $column\n";
    }
    
    echo "\nChecking if password column exists: ";
    echo Schema::hasColumn('rider_registrations', 'password') ? "YES" : "NO";
    echo "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}