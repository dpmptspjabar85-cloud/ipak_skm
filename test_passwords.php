<?php
// Test different passwords
$passwords = ['123456', 'admin', 'password', 'admin123', 'lian_permadi', 'test', '12345', 'qwerty'];

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

foreach ($passwords as $pwd) {
    $ch = curl_init('http://127.0.0.1:8001/admin/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "username=lian_permadi&password=$pwd&ipak_csrf_token=$csrf");
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (strpos($result, 'alert-error') === false && strpos($result, 'Dashboard') !== false) {
        echo "SUCCESS with password: $pwd\n";
        break;
    } else {
        echo "Failed: $pwd\n";
    }
    curl_close($ch);
}
echo "Done\n";