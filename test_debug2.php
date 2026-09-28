<?php
$cookie_file = 'D:/PTSP/ipak_skm/cookie.txt';
@unlink($cookie_file);

// Step 1: GET login page
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$response = curl_exec($ch);
curl_close($ch);

// Step 2: POST login
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin_psi',
    'password' => 'admin123',
    'ipak_csrf_token' => 'dummy',
]));
$response = curl_exec($ch);
$headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
curl_close($ch);

echo "Login response headers:\n" . $headers . "\n";
echo "\n--- Cookie file after login ---\n";
echo file_get_contents($cookie_file);
echo "\n--- Test GET users page ---\n";

$ch = curl_init('http://127.0.0.1:8001/admin/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$response = curl_exec($ch);
$headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
curl_close($ch);

echo "Users page headers:\n" . $headers . "\n";
echo "First 300 chars of body: " . substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE), 300) . "\n";