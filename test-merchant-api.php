<?php

$data = [
    'first_name' => 'Test',
    'last_name' => 'Merchant',
    'email' => 'test@example.com',
    'company_name' => 'Test Company',
    'per_parcel_rate' => 100,
    'city' => 'Karachi',
    'address' => '123 Test Street',
    'country' => 'Pakistan',
    'state' => 'Sindh',
    'zipcode' => '75500'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/merchants');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";