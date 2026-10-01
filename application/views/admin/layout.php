<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html_escape($page_title) ?> | Backoffice Survei</title>
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= ipak_asset('css/app.css') ?>">
</head>
<body class="admin-body">
<?php $section = strtolower((string) $this->uri->segment(2)); ?>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a class="admin-brand" href="<?= site_url('admin/dashboard') ?>">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="Logo DPMPTSP">
      <div>
        <strong>Backoffice Survei</strong>
        <span>DPMPTSP Jawa Barat</span>
      </div>
    </a>
    <nav class="admin-nav" aria-label="Navigasi backoffice">
      <span class="admin-nav-section">Ringkasan</span>
      <a class="<?= $section === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('admin/dashboard') ?>"><span>▦</span> Dashboard</a>

      <span class="admin-nav-section">Respons survei</span>
      <a class="<?= in_array($section, ['responses', 'detail'], true) ? 'active' : '' ?>" href="<?= site_url('admin/responses') ?>"><span>≡</span> Data Respons</a>

      <span class="admin-nav-section">Pengaturan survei</span>
      <a class="<?= $section === 'questions' ? 'active' : '' ?>" href="<?= site_url('admin/questions') ?>"><span>?</span> Pertanyaan & Jawaban</a>
      <a class="<?= $section === 'surveys' ? 'active' : '' ?>" href="<?= site_url('admin/surveys') ?>"><span>▤</span> Survei</a>
      <a class="<?= $section === 'kbli' ? 'active' : '' ?>" href="<?= site_url('admin/kbli') ?>"><span>⌕</span> KBLI</a>
      <a class="<?= $section === 'forms' && $this->input->get('type', true) !== 'shortcuts' ? 'active' : '' ?>" href="<?= site_url('admin/forms') . '?type=primary' ?>"><span>▧</span> Form</a>
      <a class="<?= $section === 'forms' && $this->input->get('type', true) === 'shortcuts' ? 'active' : '' ?>" href="<?= site_url('admin/forms') . '?type=shortcuts' ?>"><span>↗</span> Shortcut</a>

      <?php if ($admin_role === 'superadmin'): ?>
        <span class="admin-nav-section">Pengguna</span>
        <a class="<?= $section === 'users' || $section === 'users/create' || $section === 'users/edit' ? 'active' : '' ?>" href="<?= site_url('admin/users') ?>"><span>👤</span> Kelola Akun</a>
      <?php endif; ?>

      <span class="admin-nav-section">Akun saya</span>
      <a class="<?= $section === 'profile' ? 'active' : '' ?>" href="<?= site_url('admin/profile') ?>"><span>⚙</span> Profil & Password</a>

      <span class="admin-nav-section">Bantuan</span>
      <a class="<?= $section === 'help' ? 'active' : '' ?>" href="<?= site_url('admin/help') ?>"><span>i</span> Panduan Pengguna</a>
      <a href="<?= site_url('survey') ?>" target="_blank" rel="noopener"><span>↗</span> Buka Form Publik</a>
      <a href="<?= site_url('admin/logout') ?>"><span>←</span> Keluar</a>
    </nav>
    <div class="admin-user">
      <strong><?= html_escape($admin_name) ?></strong>
      <span><?= $admin_role === 'superadmin' ? 'Superadmin' : 'Administrator' ?> aktif</span>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <h1><?= html_escape($page_title) ?></h1>
        <div class="subtitle">Survei Pelayanan Terpadu · <?= date('d F Y') ?></div>
      </div>
      <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/help') ?>">Buka panduan</a>
    </header>
    <main class="admin-content">
      <?php $this->load->view($content_view); ?>
    </main>
  </div>
</div>
</body>
</html>
