<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html_escape($title) ?></title>
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body>
<main class="login-page">
  <section class="login-visual">
    <div class="agency-brand">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="Logo DPMPTSP Jawa Barat">
      <div>
        <strong>DPMPTSP Jawa Barat</strong>
        <span>Backoffice Survei Terpadu</span>
      </div>
    </div>
    <div>
      <span class="eyebrow">Ruang pengelola</span>
      <h1>Seluruh ukuran layanan dalam satu pandangan.</h1>
      <p>Pantau SKM, IPAK, nilai gabungan, tren indikator, respons, dan laporan dari satu backoffice.</p>
    </div>
    <small>Gunakan akun admin SKM yang sudah tersedia.</small>
  </section>

  <section class="login-form-side">
    <div class="login-card">
      <span class="step-kicker">Akses terbatas</span>
      <h2>Selamat datang.</h2>
      <p>Masukkan akun backoffice yang telah terdaftar pada database.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= html_escape($error) ?></div>
      <?php endif; ?>
      <?= validation_errors('<div class="alert alert-error">', '</div>') ?>

      <form action="<?= site_url('admin/login') ?>" method="post">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required maxlength="100" autocomplete="username" value="<?= html_escape(set_value('username')) ?>" placeholder="Masukkan username">
          <small class="field-help"><b>Informasi:</b> Masukkan username backoffice yang terdaftar dan masih aktif.</small>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password" placeholder="Masukkan password">
          <small class="field-help"><b>Informasi:</b> Password membedakan huruf besar, huruf kecil, angka, dan simbol.</small>
        </div>
        <button class="btn btn-primary" type="submit">Masuk ke backoffice</button>
      </form>
      <p style="margin-top:22px;text-align:center"><a class="admin-link" href="<?= site_url('survey') ?>">← Kembali ke survei</a></p>
    </div>
  </section>
</main>
</body>
</html>
