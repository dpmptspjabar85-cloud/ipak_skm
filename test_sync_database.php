<?php
// Test sync_database page after login - debug cookies
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
$result = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($result, 0, $header_size);
$body = substr($result, $header_size);
echo "=== GET login headers ===\n$header\n";
preg_match('/name="ipak_csrf_token" value="([^"]+)"/', $body, $matches);
$csrf = $matches[1] ?? '';
curl_close($ch);

$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "username=admin_psi&password=password&ipak_csrf_token=$csrf");
$result = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($result, 0, $header_size);
$body = substr($result, $header_size);
echo "=== POST login headers ===\n$header\n";
echo "=== POST login body (first 500) ===\n" . substr($body, 0, 500) . "\n";
curl_close($ch);

// Now access sync_database page
$ch = curl_init('http://127.0.0.1:8001/admin/sync-database');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
$result = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($result, 0, $header_size);
$body = substr($result, $header_size);
echo "=== GET sync-database headers ===\n$header\n";
echo "=== GET sync-database body (first 500) ===\n" . substr($body, 0, 500) . "\n";
curl_close($ch);