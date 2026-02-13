<?php

echo "Testing rider-registrations API:\n\n";

// Test data
$data = [
    'full_name' => 'Test Rider Registration',
    'email' => 'test' . time() . '@example.com',
    'mobile_primary' => '03001234567',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => '123 Test Street'
];

echo "Sending POST request to /api/rider-registrations\n";
echo "Data: " . json_encode($data) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/rider-registrations');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Check database
echo "Checking rider_registrations table:\n";
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$registration = DB::table('rider_registrations')->where('email', $data['email'])->first();
if ($registration) {
    echo "✅ Entry found in rider_registrations table\n";
    echo "ID: " . $registration->id . "\n";
    echo "Full Name: " . $registration->full_name . "\n";
    echo "Status: " . $registration->status . "\n";
} else {
    echo "❌ No entry found in rider_registrations table\n";
}

// Check users table
$user = DB::table('users')->where('email', $data['email'])->first();
if ($user) {
    echo "✅ Entry also created in users table\n";
    echo "ID: " . $user->id . "\n";
    echo "Name: " . $user->first_name . " " . $user->last_name . "\n";
    echo "Role: " . $user->role . "\n";
} else {
    echo "❌ No entry found in users table\n";
}

?>