<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Survei berhasil dikirim | <?= html_escape($agency) ?></title>
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body>
<main class="success-page">
  <section class="success-card">
    <div class="success-icon" aria-hidden="true">✓</div>
    <span class="step-kicker">Survei berhasil disimpan</span>
    <h1>Terima kasih atas partisipasi Anda.</h1>
    <p>Jawaban Anda telah diterima dan disimpan sebagai hasil evaluasi pelayanan DPMPTSP Provinsi Jawa Barat.</p>
    <?php if (!empty($survey_results)): ?>
      <div class="stats-grid" style="margin:22px 0;text-align:left">
        <?php foreach ($survey_results as $result): ?>
          <article class="stat-card">
            <span><?= html_escape($result['index_label']) ?></span>
            <strong><?= number_format((float) $result['score'], 2) ?></strong>
            <small style="color:<?= html_escape($result['category_color']) ?>"><?= html_escape($result['category_label']) ?></small>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="reference-box"><?= html_escape($reference) ?></div>
    <p>Simpan nomor referensi di atas jika diperlukan.</p>
    <div class="success-actions">
      <a class="btn btn-primary" href="<?= site_url('/') ?>">Lihat dashboard</a>
      <a class="btn btn-secondary" href="<?= site_url('surveys') ?>">Pilih survei lainnya</a>
    </div>
  </section>
</main>
</body>
</html>
