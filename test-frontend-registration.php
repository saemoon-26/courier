<?php

// Test with multipart form data like frontend sends
$data = [
    'full_name' => 'Test Frontend Rider',
    'email' => 'frontend' . time() . '@example.com',
    'mobile_primary' => '03001234567',
    'city' => 'Karachi',
    'state' => 'Sindh',
    'address' => '123 Test Street'
];

// Create multipart form data
$boundary = '----formdata' . uniqid();
$postData = '';

foreach ($data as $key => $value) {
    $postData .= "--$boundary\r\n";
    $postData .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
    $postData .= "$value\r\n";
}
$postData .= "--$boundary--\r\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/rider-registrations');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: multipart/form-data; boundary=' . $boundary,
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

?>