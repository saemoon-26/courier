<?php

$data = [
    'full_name' => 'Test Rider',
    'email' => 'test@example.com',
    'mobile_primary' => '03001234567',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => 'Test Address',
    'vehicle_type' => 'Bike'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/rider-registrations');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
echo "Response: $response\n";