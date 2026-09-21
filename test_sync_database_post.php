<?php
// Test sync_database POST with proper CSRF token
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
$result = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($result, $header_size);
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
curl_setopt($ch, CURLOPT_POSTFIELDS, "username=admin_psi&password=password&ipak_csrf_token=$csrf");
$result = curl_exec($ch);
curl_close($ch);

// Get sync-database page to get new CSRF token
$ch = curl_init('http://127.0.0.1:8001/admin/sync-database');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$result = curl_exec($ch);
preg_match('/name="ipak_csrf_token" value="([^"]+)"/', $result, $matches);
$csrf = $matches[1] ?? '';
echo "Sync database CSRF: $csrf\n";
curl_close($ch);

// Now POST to sync-database with correct CSRF token
$ch = curl_init('http://127.0.0.1:8001/admin/sync-database');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_POSTFIELDS, "ipak_csrf_token=$csrf");
$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "POST sync-database: $code\n";
echo $result;
curl_close($ch);