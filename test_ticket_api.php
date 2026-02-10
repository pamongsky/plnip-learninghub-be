<?php
// Get auth token first
$ch = curl_init('http://192.168.4.177:3000/api/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'admin@plnip.local',
    'password' => 'password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$loginResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($loginResponse['token'])) {
    die("Login failed\n");
}

$token = $loginResponse['token'];
echo "Token: $token\n\n";

// Create ticket
$ch = curl_init('http://192.168.4.177:3000/api/support/tickets');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'subject' => 'Test dari PHP script langsung',
    'description' => 'Ini test untuk memastikan API endpoint bekerja dengan benar tanpa frontend',
    'category' => 'technical',
    'priority' => 'medium'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
print_r(json_decode($response, true));
