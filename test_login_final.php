<?php
$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$result = curl_exec($ch);
preg_match('/name="ipak_csrf_token" value="([^"]+)"/', $result, $matches);
$csrf = $matches[1] ?? '';
curl_close($ch);

echo "CSRF: $csrf\n";

$ch = curl_init('http://127.0.0.1:8001/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_POSTFIELDS, "username=admin_psi&password=password&ipak_csrf_token=$csrf");
$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (strpos($result, 'alert-error') === false && strpos($result, 'Dashboard') !== false) {
    echo "SUCCESS with admin_psi / password\n";
} else {
    echo "Failed\n";
    echo $result;
}
curl_close($ch);