<?php
$cookie_file = 'D:/PTSP/ipak_skm/cookie.txt';
@unlink($cookie_file);

function curl_post($url, $data, $cookie_file) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    $headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);
    return ['headers' => $headers, 'body' => $body];
}

function curl_get($url, $cookie_file) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    $response = curl_exec($ch);
    $headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $body = substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);
    return ['headers' => $headers, 'body' => $body];
}

function get_csrf_token($html) {
    if (preg_match('/name="ipak_csrf_token" value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return null;
}

// Login
$resp = curl_get('http://127.0.0.1:8001/admin/login', $cookie_file);
$token = get_csrf_token($resp['body']);
curl_post('http://127.0.0.1:8001/admin/login', ['username' => 'admin_psi', 'password' => 'admin123', 'ipak_csrf_token' => $token], $cookie_file);
echo "Login: OK\n";

// Get create form
$resp = curl_get('http://127.0.0.1:8001/admin/users/create', $cookie_file);
$token = get_csrf_token($resp['body']);
echo "CSRF from create form: " . ($token ? $token : 'NOT FOUND') . "\n";

// Create user
$resp = curl_post('http://127.0.0.1:8001/admin/users/create', [
    'username' => 'testuser',
    'nama' => 'Test User',
    'password' => 'test123456',
    'role_name' => 'admin',
    'ipak_csrf_token' => $token,
], $cookie_file);

echo "\n--- Response Headers ---\n" . $resp['headers'] . "\n";
echo "\n--- Response Body (first 1000 chars) ---\n" . substr($resp['body'], 0, 1000) . "\n";