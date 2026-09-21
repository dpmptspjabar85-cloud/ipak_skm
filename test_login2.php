<?php
// Get login page with CSRF token
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
$result = curl_exec($ch);
echo "GET login page: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;

// Extract CSRF token
preg_match('/name="ipak_csrf_token" value="([^"]+)"/', $result, $matches);
$csrf = $matches[1] ?? '';
echo "CSRF: $csrf" . PHP_EOL;

curl_close($ch);

// Now login
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_POSTFIELDS, "username=lian_permadi&password=password&ipak_csrf_token=$csrf");
$result = curl_exec($ch);
echo "POST login: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo $result;
curl_close($ch);