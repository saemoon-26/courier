<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== KARACHI RIDERS STATUS ===\n\n";

$karachiRiders = DB::table('users')
    ->join('address', 'users.id', '=', 'address.user_id')
    ->where('users.role', 'rider')
    ->where('users.status', 'active')
    ->where(function($query) {
        $query->where('address.city', 'LIKE', '%karachi%')
              ->orWhere('address.city', 'LIKE', '%Karachi%');
    })
    ->select('users.id', 'users.first_name', 'users.last_name', 'users.status', 'address.city')
    ->get();

foreach ($karachiRiders as $rider) {
    $activeParcels = DB::table('parcel')
        ->where('assigned_to', $rider->id)
        ->whereIn('parcel_status', ['pending', 'picked_up', 'in_transit', 'out_for_delivery'])
        ->count();
    
    $available = ($rider->status === 'active' && $activeParcels < 5) ? 'YES ✅' : 'NO ❌';
    $reason = '';
    if ($rider->status !== 'active') $reason = '(Status: ' . $rider->status . ')';
    if ($activeParcels >= 5) $reason .= ' (Parcels: ' . $activeParcels . '/5)';
    
    echo "ID: {$rider->id} | {$rider->first_name} {$rider->last_name} | City: {$rider->city} | Available: {$available} {$reason}\n";
}

echo "\n=== SOLUTION ===\n";
echo "Option 1: Increase limit from 5 to 10 parcels per rider\n";
echo "Option 2: Mark some parcels as 'delivered' to free up riders\n";
echo "Option 3: Add more riders in Karachi\n";
