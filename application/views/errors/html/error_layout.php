<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$isProduction = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
$displayHeading = $isProduction
    ? (isset($production_heading) ? $production_heading : 'Layanan sedang mengalami gangguan')
    : (isset($error_heading) ? $error_heading : 'Terjadi Kesalahan');
$displayMessage = $isProduction
    ? (isset($production_message) ? $production_message : 'Permintaan belum dapat diproses. Silakan coba kembali beberapa saat lagi.')
    : (isset($error_message) ? $error_message : '');
if (is_array($displayMessage)) {
    $displayMessage = implode("\n", $displayMessage);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string) $displayHeading, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;color:#172033;background:#f5f7fb;font-family:Arial,sans-serif}
    main{width:min(620px,100%);padding:32px;box-sizing:border-box;border:1px solid #e1e5ef;border-radius:16px;background:#fff;box-shadow:0 20px 55px rgba(21,35,85,.1)}
    b{display:inline-block;margin-bottom:12px;color:#3049d8;font-size:12px;letter-spacing:.08em;text-transform:uppercase}
    h1{margin:0 0 14px;font-family:Georgia,serif;font-size:34px;line-height:1.12}
    p,pre{margin:0;color:#626c7e;font-size:13px;line-height:1.7}
    pre{overflow:auto;padding:14px;border-radius:10px;background:#f6f7fb;white-space:pre-wrap;word-break:break-word}
  </style>
</head>
<body>
<main>
  <b>Sistem Survei Pelayanan</b>
  <h1><?= htmlspecialchars((string) $displayHeading, ENT_QUOTES, 'UTF-8') ?></h1>
  <?php if ($isProduction): ?>
    <p><?= htmlspecialchars((string) $displayMessage, ENT_QUOTES, 'UTF-8') ?></p>
  <?php else: ?>
    <pre><?= htmlspecialchars(strip_tags((string) $displayMessage), ENT_QUOTES, 'UTF-8') ?></pre>
  <?php endif; ?>
</main>
</body>
</html>
