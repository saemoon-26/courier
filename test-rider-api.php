<?php

// Test the rider registration API
$url = 'http://127.0.0.1:8000/api/rider-registrations';

$data = [
    'full_name' => 'Test Rider API',
    'father_name' => 'Test Father',
    'email' => 'test.rider.api@example.com',
    'mobile_primary' => '03001234567',
    'mobile_alternate' => '03007654321',
    'cnic_number' => '1234567890123',
    'vehicle_type' => 'Bike',
    'vehicle_brand' => 'Honda',
    'vehicle_model' => 'CD 70',
    'vehicle_registration' => 'ABC-123',
    'driving_license_number' => 'DL123456',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => 'Test Address, Block A, Karachi',
    'bank_name' => 'Test Bank',
    'account_number' => '1234567890',
    'account_title' => 'Test Rider API'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "Error: $error\n";
} else {
    echo "Response: $response\n";
}