<?php

require_once 'vendor/autoload.php';

// Test Rider Registration and Login System

function testAPI($url, $data = null, $method = 'GET', $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://localhost:8000/api" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

echo "=== RIDER AUTHENTICATION SYSTEM TEST ===\n\n";

// Test 1: Register a new rider
echo "1. Testing Rider Registration...\n";
$registrationData = [
    'full_name' => 'Test Rider',
    'father_name' => 'Test Father',
    'email' => 'testrider@example.com',
    'password' => 'password123',
    'mobile_primary' => '03001234567',
    'vehicle_type' => 'Bike',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => 'Test Address, Karachi'
];

$result = testAPI('/rider-registrations', $registrationData, 'POST');
echo "Status: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

if ($result['status_code'] === 201) {
    $registrationId = $result['response']['data']['registration_id'];
    
    // Test 2: Try to login before approval (should fail)
    echo "2. Testing Login Before Approval (should fail)...\n";
    $loginResult = testAPI('/rider/login', [
        'email' => 'testrider@example.com',
        'password' => 'password123'
    ], 'POST');
    echo "Status: " . $loginResult['status_code'] . "\n";
    echo "Response: " . json_encode($loginResult['response'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 3: Approve the rider
    echo "3. Testing Rider Approval...\n";
    $approveResult = testAPI("/rider-registrations/{$registrationId}/approve", [], 'POST');
    echo "Status: " . $approveResult['status_code'] . "\n";
    echo "Response: " . json_encode($approveResult['response'], JSON_PRETTY_PRINT) . "\n\n";
    
    if ($approveResult['status_code'] === 200) {
        // Test 4: Login after approval (should work)
        echo "4. Testing Login After Approval...\n";
        $loginResult = testAPI('/rider/login', [
            'email' => 'testrider@example.com',
            'password' => 'password123'
        ], 'POST');
        echo "Status: " . $loginResult['status_code'] . "\n";
        echo "Response: " . json_encode($loginResult['response'], JSON_PRETTY_PRINT) . "\n\n";
        
        if ($loginResult['status_code'] === 200 && isset($loginResult['response']['token'])) {
            $token = $loginResult['response']['token'];
            
            // Test 5: Access rider profile
            echo "5. Testing Rider Profile Access...\n";
            $profileResult = testAPI('/rider/profile', null, 'GET', $token);
            echo "Status: " . $profileResult['status_code'] . "\n";
            echo "Response: " . json_encode($profileResult['response'], JSON_PRETTY_PRINT) . "\n\n";
        }
    }
}

echo "=== TEST COMPLETED ===\n";