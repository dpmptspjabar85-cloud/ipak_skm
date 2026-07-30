<?php
$typeLabels = [
    'all' => 'Semua jenis',
    'skm' => 'Memuat SKM',
    'regular' => 'Tanpa nomor resi',
    'combined' => 'Survei gabungan',
];

$catalogUrl = function ($targetPage) use ($search, $type) {
    $query = ['page' => max(1, (int) $targetPage)];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($type !== 'all') {
        $query['type'] = $type;
    }
    return site_url('surveys') . '?' . http_build_query($query);
};
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pilih Survei | <?= html_escape($agency) ?></title>
  <meta name="description" content="Pilih survei pelayanan publik yang ingin Anda isi">
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body class="public-dashboard survey-catalog-page">
<header class="public-header">
  <div class="public-container public-nav">
    <a class="public-brand" href="<?= site_url('/') ?>" aria-label="Dashboard Survei">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="">
      <span>
        <strong>Survei Pelayanan</strong>
        <small>DPMPTSP Jawa Barat</small>
      </span>
    </a>
    <nav class="public-nav-links" aria-label="Navigasi utama">
      <a href="<?= site_url('/') ?>">Dashboard</a>
      <a href="<?= site_url('surveys') ?>" aria-current="page">Pilih Survei</a>
      <a href="<?= site_url('admin/login') ?>">Backoffice</a>
    </nav>
  </div>
</header>

<main>
  <section class="catalog-hero">
    <div class="public-container catalog-hero-inner">
      <div>
        <span class="public-eyebrow">Pusat Formulir Survei</span>
        <h1>Pilih survei yang ingin Anda isi.</h1>
        <p>Setiap kartu membuka formulir resmi sesuai kebutuhan. Survei yang memuat SKM akan meminta nomor resi izin yang sudah terbit.</p>
      </div>
      <div class="catalog-guide" aria-label="Panduan singkat">
        <b>3 langkah mudah</b>
        <span>1. Cari survei</span>
        <span>2. Buka formulir</span>
        <span>3. Isi dan kirim jawaban</span>
      </div>
    </div>
  </section>

  <section class="public-section catalog-section">
    <div class="public-container">
      <form class="catalog-filter" method="get" action="<?= site_url('surveys') ?>" role="search">
        <div class="catalog-search-field">
          <label for="catalog-search">Cari survei</label>
          <input
            id="catalog-search"
            type="search"
            name="q"
            value="<?= html_escape($search) ?>"
            placeholder="Contoh: SKM, NIB, atau nama layanan"
            maxlength="80"
          >
          <small>Ketik nama, kode, atau isi singkat survei.</small>
        </div>
        <div class="catalog-type-field">
          <label for="catalog-type">Jenis formulir</label>
          <select id="catalog-type" name="type">
            <?php foreach ($typeLabels as $value => $label): ?>
              <option value="<?= html_escape($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Gunakan filter agar daftar lebih ringkas.</small>
        </div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
      </form>

      <div class="catalog-result-head">
        <div>
          <span class="section-kicker">Formulir aktif</span>
          <h2><?= number_format($total_forms) ?> formulir tersedia</h2>
          <p>
            <?php if ($search !== ''): ?>
              Hasil pencarian untuk “<?= html_escape($search) ?>” · <?= html_escape($typeLabels[$type]) ?>
            <?php else: ?>
              <?= html_escape($typeLabels[$type]) ?> · diurutkan dari formulir utama
            <?php endif; ?>
          </p>
        </div>
        <?php if ($search !== '' || $type !== 'all'): ?>
          <a class="catalog-reset" href="<?= site_url('surveys') ?>">Hapus pencarian dan filter</a>
        <?php endif; ?>
      </div>

      <?php if ($forms): ?>
        <div class="survey-shortcut-grid catalog-card-grid">
          <?php foreach ($forms as $form): ?>
            <?php
              $surveyUrl = site_url('survey') . '?form=' . rawurlencode($form['form_code']);
              $visibleNames = array_slice($form['survey_names'], 0, 3);
              $remainingNames = max(0, count($form['survey_names']) - count($visibleNames));
            ?>
            <article class="survey-shortcut-card" style="--card-accent:<?= html_escape($form['accent_color']) ?>">
              <div class="shortcut-card-top">
                <span class="shortcut-kind">
                  <?= $form['survey_count'] > 1 ? 'Survei gabungan' : 'Survei mandiri' ?>
                </span>
                <?php if ($form['is_default']): ?><span class="shortcut-default">Form utama</span><?php endif; ?>
              </div>
              <div class="shortcut-card-copy">
                <h3><?= html_escape($form['form_name']) ?></h3>
                <p><?= html_escape($form['description'] ?: 'Formulir evaluasi pelayanan publik.') ?></p>
              </div>
              <div class="shortcut-surveys" aria-label="Survei di dalam formulir">
                <?php foreach ($visibleNames as $surveyName): ?>
                  <span><?= html_escape($surveyName) ?></span>
                <?php endforeach; ?>
                <?php if ($remainingNames): ?><span>+<?= $remainingNames ?> lainnya</span><?php endif; ?>
              </div>
              <div class="shortcut-meta">
                <span><?= number_format($form['question_count']) ?> pertanyaan</span>
                <span><?= $form['requires_resi'] ? 'Nomor resi wajib' : 'Tanpa nomor resi SKM' ?></span>
              </div>
              <a class="btn btn-primary shortcut-open" href="<?= $surveyUrl ?>">
                <?= $form['requires_resi'] ? 'Masukkan resi dan isi' : 'Isi survei ini' ?>
              </a>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
          <nav class="catalog-pagination" aria-label="Halaman daftar survei">
            <?php if ($page > 1): ?>
              <a href="<?= html_escape($catalogUrl($page - 1)) ?>">Sebelumnya</a>
            <?php else: ?>
              <span class="is-disabled">Sebelumnya</span>
            <?php endif; ?>

            <span class="pagination-status">Halaman <?= number_format($page) ?> dari <?= number_format($total_pages) ?></span>

            <?php if ($page < $total_pages): ?>
              <a href="<?= html_escape($catalogUrl($page + 1)) ?>">Berikutnya</a>
            <?php else: ?>
              <span class="is-disabled">Berikutnya</span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="catalog-empty">
          <strong>Survei tidak ditemukan.</strong>
          <p>Coba gunakan kata pencarian yang lebih singkat atau tampilkan semua jenis formulir.</p>
          <a class="btn btn-primary" href="<?= site_url('surveys') ?>">Lihat semua survei</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="public-footer">
  <div class="public-container">
    <span>© <?= date('Y') ?> DPMPTSP Provinsi Jawa Barat</span>
    <span>Formulir yang tampil hanya survei aktif dan siap diisi</span>
  </div>
</footer>
</body>
</html>
