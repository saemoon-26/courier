<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== RIDERS IN DATABASE ===\n\n";

$riders = DB::table('users')
    ->join('address', 'users.id', '=', 'address.user_id')
    ->where('users.role', 'rider')
    ->where('users.status', 'active')
    ->select('users.id', 'users.first_name', 'users.last_name', 'address.city')
    ->get();

foreach ($riders as $rider) {
    echo "ID: {$rider->id} | Name: {$rider->first_name} {$rider->last_name} | City: {$rider->city}\n";
}

echo "\n=== RECENT PARCELS ===\n\n";

$parcels = DB::table('parcel')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['parcel_id', 'tracking_code', 'pickup_city', 'assigned_to']);

foreach ($parcels as $parcel) {
    echo "ID: {$parcel->parcel_id} | Tracking: {$parcel->tracking_code} | City: {$parcel->pickup_city} | Assigned: " . ($parcel->assigned_to ?? 'NULL') . "\n";
}
