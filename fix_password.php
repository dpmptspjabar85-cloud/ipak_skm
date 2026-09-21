<?php
require_once 'D:\PTSP\ipak_skm\index.php';

// Use CodeIgniter's database
$CI = &get_instance();
$CI->load->database();

$hash = password_hash('password', PASSWORD_BCRYPT);
echo "Hash: $hash\n";

$CI->db->where('username', 'admin_psi')->update('skm_cms_user', ['password' => $hash]);
echo "Updated: " . $CI->db->affected_rows() . " rows\n";

// Verify
$result = $CI->db->where('username', 'admin_psi')->get('skm_cms_user')->row_array();
echo "New password: " . $result['password'] . "\n";
echo "Length: " . strlen($result['password']) . "\n";

// Test verify
echo "Verify: " . (password_verify('password', $result['password']) ? 'OK' : 'FAIL') . "\n";