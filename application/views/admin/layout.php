<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html_escape($page_title) ?> | Backoffice Survei</title>
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
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
      <a class="<?= in_array($section, ['surveys', 'forms'], true) ? 'active' : '' ?>" href="<?= site_url('admin/forms') ?>"><span>▤</span> Survei, Form & Shortcut</a>

      <?php if ($admin_role === 'superadmin'): ?>
        <span class="admin-nav-section">Integrasi data</span>
        <a class="<?= $section === 'api-builder' ? 'active' : '' ?>" href="<?= site_url('admin/api-builder') ?>"><span>&lt;/&gt;</span> API Builder</a>
      <?php endif; ?>

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
