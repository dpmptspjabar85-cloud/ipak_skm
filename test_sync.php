<?php
$ch = curl_init('http://127.0.0.1:8001/admin/sync-database');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$result = curl_exec($ch);
echo curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo $result;
curl_close($ch);