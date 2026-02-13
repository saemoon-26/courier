<?php

require_once 'vendor/autoload.php';

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
    
    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

echo "=== RIDER STATUS TRACKING TEST ===\n\n";

// Test 1: Register a new rider
echo "1. Testing Rider Registration...\n";
$registrationData = [
    'full_name' => 'Status Test Rider',
    'father_name' => 'Test Father',
    'email' => 'statustest@example.com',
    'password' => 'password123',
    'mobile_primary' => '03001234568',
    'vehicle_type' => 'Bike',
    'city' => 'Lahore',
    'state' => 'Punjab',
    'address' => 'Test Address, Lahore'
];

$result = testAPI('/rider-registrations', $registrationData, 'POST');
echo "Status: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

if ($result['status_code'] === 201) {
    $registrationId = $result['response']['data']['registration_id'];
    
    // Test 2: Check status (should be pending)
    echo "2. Testing Status Check (should be pending)...\n";
    $statusResult = testAPI('/rider-registrations/check-status', [
        'email' => 'statustest@example.com'
    ], 'POST');
    echo "Status: " . $statusResult['status_code'] . "\n";
    echo "Response: " . json_encode($statusResult['response'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 3: Reject the rider with reason
    echo "3. Testing Rider Rejection with Reason...\n";
    $rejectResult = testAPI("/rider-registrations/{$registrationId}/reject", [
        'rejection_reason' => 'Incomplete documents submitted. Please resubmit with all required documents.'
    ], 'POST');
    echo "Status: " . $rejectResult['status_code'] . "\n";
    echo "Response: " . json_encode($rejectResult['response'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 4: Check status after rejection
    echo "4. Testing Status Check After Rejection...\n";
    $statusResult = testAPI('/rider-registrations/check-status', [
        'email' => 'statustest@example.com'
    ], 'POST');
    echo "Status: " . $statusResult['status_code'] . "\n";
    echo "Response: " . json_encode($statusResult['response'], JSON_PRETTY_PRINT) . "\n\n";
}

// Test 5: Register another rider for approval test
echo "5. Testing Another Registration for Approval...\n";
$registrationData2 = [
    'full_name' => 'Approval Test Rider',
    'father_name' => 'Test Father',
    'email' => 'approvaltest@example.com',
    'password' => 'password123',
    'mobile_primary' => '03001234569',
    'vehicle_type' => 'Car',
    'city' => 'Islamabad',
    'state' => 'ICT',
    'address' => 'Test Address, Islamabad'
];

$result2 = testAPI('/rider-registrations', $registrationData2, 'POST');
echo "Status: " . $result2['status_code'] . "\n";

if ($result2['status_code'] === 201) {
    $registrationId2 = $result2['response']['data']['registration_id'];
    
    // Test 6: Approve the rider
    echo "6. Testing Rider Approval...\n";
    $approveResult = testAPI("/rider-registrations/{$registrationId2}/approve", [], 'POST');
    echo "Status: " . $approveResult['status_code'] . "\n";
    echo "Response: " . json_encode($approveResult['response'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Test 7: Check status after approval
    echo "7. Testing Status Check After Approval...\n";
    $statusResult = testAPI('/rider-registrations/check-status', [
        'email' => 'approvaltest@example.com'
    ], 'POST');
    echo "Status: " . $statusResult['status_code'] . "\n";
    echo "Response: " . json_encode($statusResult['response'], JSON_PRETTY_PRINT) . "\n\n";
}

echo "=== STATUS TRACKING TEST COMPLETED ===\n";