<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Akses Survei Pelayanan | <?= html_escape($agency) ?></title>
  <meta name="description" content="Masukkan nomor resi izin terbit untuk mengisi survei pelayanan terpadu">
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body>
<main class="access-page">
  <section class="access-visual">
    <div class="agency-brand">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="Logo DPMPTSP Jawa Barat">
      <div>
        <strong>DPMPTSP Jawa Barat</strong>
        <span>Pelayanan berkualitas, berintegritas, dan terpercaya</span>
      </div>
    </div>

    <div class="access-visual-copy">
      <span class="eyebrow">Survei pasca-terbit</span>
      <h1>Penilaian dimulai setelah izin Anda terbit.</h1>
      <p>
        Nomor resi menghubungkan penilaian dengan permohonan yang tepat.
        Data izin akan diperiksa dan diisi otomatis tanpa mengubah informasi permohonan.
      </p>
      <div class="access-steps">
        <div><b>1</b><span>Masukkan nomor resi</span></div>
        <div><b>2</b><span>Verifikasi status izin</span></div>
        <div><b>3</b><span>Berikan penilaian</span></div>
      </div>
    </div>

    <div class="access-public-links">
      <a class="access-dashboard-link" href="<?= site_url('surveys') ?>">← Pilih survei lain</a>
      <a class="access-dashboard-link" href="<?= site_url('/') ?>">Lihat dashboard</a>
    </div>
  </section>

  <section class="access-form-side">
    <div class="access-card">
      <span class="step-kicker">Verifikasi permohonan</span>
      <h2>Masukkan nomor resi izin</h2>
      <p>Survei hanya dapat diisi untuk permohonan yang izinnya sudah terbit atau selesai.</p>

      <?php if (!empty($access_message)): ?>
        <div class="access-message access-message-<?= html_escape($access_status) ?>" role="status">
          <?= html_escape($access_message) ?>
        </div>
      <?php endif; ?>

      <form method="get" action="<?= site_url('survey') ?>" class="resi-form">
        <?php if (!empty($form_code)): ?>
          <input type="hidden" name="form" value="<?= html_escape($form_code) ?>">
        <?php endif; ?>
        <div class="field">
          <label for="resi">Nomor resi <span class="required-mark">*</span></label>
          <input
            id="resi"
            name="resi"
            type="text"
            maxlength="20"
            inputmode="numeric"
            autocomplete="off"
            required
            value="<?= html_escape($resi) ?>"
            placeholder="Masukkan nomor resi permohonan">
          <small class="field-help"><b>Informasi:</b> Gunakan nomor resi yang tercantum pada permohonan atau dokumen izin. Tanda baca yang diperbolehkan: titik, garis bawah, dan tanda hubung.</small>
        </div>
        <button class="btn btn-primary" type="submit">Periksa dan buka survei</button>
      </form>

      <div class="access-help">
        <strong>Link dari sistem perizinan</strong>
        <p>
          Halaman ini juga menerima parameter GET
          <code>?resi=...</code>, <code>?nomor_resi=...</code>, atau <code>?CODE=...</code>.
        </p>
      </div>
    </div>
  </section>
</main>
</body>
</html>
