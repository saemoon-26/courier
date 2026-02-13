<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "=== COURIER API ENDPOINTS TEST ===\n\n";

$baseUrl = 'http://localhost:8000/api';

function testEndpoint($method, $url, $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// Start Laravel server in background
echo "Starting Laravel development server...\n";
$serverCommand = 'start /B php artisan serve --host=127.0.0.1 --port=8000';
exec($serverCommand);
sleep(3); // Wait for server to start

echo "Testing API endpoints:\n\n";

// Test 1: Basic endpoints
echo "1. Testing Basic Endpoints\n";
echo "--------------------------\n";

$endpoints = [
    ['GET', '/parcels', 'Get all parcels'],
    ['GET', '/riders', 'Get all riders'],
    ['GET', '/merchants', 'Get all merchants'],
];

foreach ($endpoints as [$method, $endpoint, $description]) {
    $result = testEndpoint($method, $baseUrl . $endpoint);
    
    if ($result['error']) {
        echo "❌ $description: Connection error - " . $result['error'] . "\n";
    } elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
        echo "✅ $description: HTTP {$result['status_code']}\n";
    } else {
        echo "⚠️  $description: HTTP {$result['status_code']}\n";
    }
}

echo "\n2. Testing POST Endpoints\n";
echo "-------------------------\n";

// Test rider registration
$riderData = [
    'full_name' => 'Test API Rider',
    'email' => 'test.api@example.com',
    'mobile_primary' => '03001234567',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => 'Test Address',
    'vehicle_type' => 'Bike'
];

$result = testEndpoint('POST', $baseUrl . '/rider-registrations', $riderData);
if ($result['error']) {
    echo "❌ Rider registration: Connection error\n";
} elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
    echo "✅ Rider registration: HTTP {$result['status_code']}\n";
} else {
    echo "⚠️  Rider registration: HTTP {$result['status_code']}\n";
}

// Test user registration
$userData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test.user@example.com',
    'password' => 'password123',
    'role' => 'rider'
];

$result = testEndpoint('POST', $baseUrl . '/register', $userData);
if ($result['error']) {
    echo "❌ User registration: Connection error\n";
} elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
    echo "✅ User registration: HTTP {$result['status_code']}\n";
} else {
    echo "⚠️  User registration: HTTP {$result['status_code']}\n";
}

echo "\n3. Testing Tracking Endpoint\n";
echo "----------------------------\n";

$result = testEndpoint('GET', $baseUrl . '/parcels/track/TEST123');
if ($result['error']) {
    echo "❌ Parcel tracking: Connection error\n";
} elseif ($result['status_code'] == 404) {
    echo "✅ Parcel tracking: HTTP 404 (expected - no parcel with TEST123)\n";
} elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
    echo "✅ Parcel tracking: HTTP {$result['status_code']}\n";
} else {
    echo "⚠️  Parcel tracking: HTTP {$result['status_code']}\n";
}

echo "\n4. Testing AI Assignment Endpoint\n";
echo "---------------------------------\n";

$result = testEndpoint('POST', $baseUrl . '/auto-assign-pending');
if ($result['error']) {
    echo "❌ AI assignment: Connection error\n";
} elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
    echo "✅ AI assignment: HTTP {$result['status_code']}\n";
} else {
    echo "⚠️  AI assignment: HTTP {$result['status_code']}\n";
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "API ENDPOINT TESTING COMPLETED\n";
echo str_repeat("=", 40) . "\n";

// Stop the server
echo "\nStopping Laravel server...\n";
exec('taskkill /F /IM php.exe 2>nul', $output, $return);