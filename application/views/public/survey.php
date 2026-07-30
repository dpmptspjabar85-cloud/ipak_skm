<?php
$respondentFields = !empty($form_definition['respondent_fields']) ? $form_definition['respondent_fields'] : [];
$hasIdentityFields = false;
$hasAccessFields = false;
foreach ($respondentFields as $respondentField) {
    if ($respondentField['field_mode'] === 'hidden') {
        continue;
    }
    if ($respondentField['field_group'] === 'access') {
        $hasAccessFields = true;
    } else {
        $hasIdentityFields = true;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html_escape($title) ?> | <?= html_escape($agency) ?></title>
  <meta name="description" content="Survei pelayanan terpadu DPMPTSP Provinsi Jawa Barat">
  <link rel="icon" href="<?= base_url('assets/theme_skm/img/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/ipak/css/app.css') ?>">
</head>
<body>
<main class="survey-shell">
  <section class="survey-intro" aria-labelledby="survey-title">
    <div class="agency-brand">
      <img src="<?= base_url('assets/images/logo-dinas.png') ?>" alt="Logo DPMPTSP Jawa Barat">
      <div><strong>DPMPTSP Jawa Barat</strong><span>Pelayanan berkualitas, berintegritas, dan terpercaya</span></div>
    </div>
    <div class="intro-copy">
      <span class="eyebrow"><?= html_escape($form_definition['form_name']) ?> <?= date('Y') ?></span>
      <h1 id="survey-title">Satu survei untuk layanan yang lebih baik.</h1>
      <p>Jawaban Anda menjadi dasar perbaikan mutu, kepuasan, transparansi, dan integritas pelayanan publik.</p>
      <div class="trust-list">
        <div class="trust-item"><b>± 4 menit</b>Waktu pengisian</div>
        <div class="trust-item"><b><?= count($question_definitions) ?> indikator</b>Satu alur pengisian terpadu</div>
        <div class="trust-item"><b>Data terlindungi</b>Dipakai untuk evaluasi layanan</div>
        <div class="trust-item"><b>Mudah diisi</b>Dapat diisi dari ponsel</div>
      </div>
    </div>
    <div class="intro-footer">
      <span>© <?= date('Y') ?> DPMPTSP Provinsi Jawa Barat</span>
      <span><?= count($form_definition['surveys']) ?> ukuran evaluasi dalam satu form</span>
    </div>
  </section>

  <section class="survey-workspace">
    <div class="workspace-top">
      <div>
        <div id="step-caption" class="step-caption">Langkah 1</div>
        <div class="progress-track" aria-hidden="true"><div id="progress-value" class="progress-value"></div></div>
      </div>
      <div class="workspace-links">
        <a class="admin-link" href="<?= site_url('/') ?>">Dashboard</a>
        <a class="admin-link" href="<?= site_url('surveys') ?>">Pilih survei</a>
        <a class="admin-link" href="<?= site_url('admin/login') ?>">Backoffice</a>
      </div>
    </div>

    <div class="form-wrap">
      <?php if ($this->session->flashdata('save_error')): ?>
        <div class="alert alert-error"><?= html_escape($this->session->flashdata('save_error')) ?></div>
      <?php endif; ?>
      <?php if (!empty($validation_errors)): ?>
        <div class="alert alert-error" role="alert">
          <strong>Mohon periksa kembali isian Anda.</strong>
          <ul><?php foreach ($validation_errors as $error): ?><li><?= html_escape(strip_tags($error)) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form id="ipak-survey-form" action="<?= site_url('survey/submit') ?>" method="post" novalidate>
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <input type="hidden" name="resi" value="<?= html_escape($resi) ?>">
        <input type="hidden" name="form_code" value="<?= html_escape($form_code) ?>">

        <section class="wizard-step is-active" data-step="access">
          <span class="step-kicker">Langkah awal</span>
          <h2><?= $requires_resi ? 'Izin sudah terverifikasi. Lengkapi input awal.' : 'Isi data awal untuk membuka survei.' ?></h2>
          <p class="step-lead">
            <?= $requires_resi
              ? 'Nomor resi hanya digunakan untuk SKM dan tidak dapat diganti pada langkah ini.'
              : 'Kolom pada langkah awal dipilih oleh pengelola dan wajib diisi.' ?>
          </p>

          <?php if ($requires_resi): ?>
            <div class="permit-summary">
              <div class="permit-summary-head"><span>Izin terverifikasi</span><b><?= html_escape($permit['status_berkas']) ?></b></div>
              <dl>
                <div><dt>Nomor resi SKM</dt><dd><?= html_escape($resi) ?></dd></div>
                <div><dt>Jenis izin</dt><dd><?= html_escape($permit['permit_name'] ?: '-') ?></dd></div>
                <div><dt>Sektor</dt><dd><?= html_escape($permit['sector_name'] ?: '-') ?></dd></div>
              </dl>
            </div>
          <?php else: ?>
            <div class="respondent-info-box">
              <strong>Form ini tidak memerlukan nomor resi.</strong>
              <span>Isi seluruh kolom pada langkah awal sebelum melanjutkan.</span>
            </div>
          <?php endif; ?>

          <?php $active_group = 'access'; $this->load->view('public/_respondent_fields', get_defined_vars()); ?>
          <div class="wizard-actions">
            <span></span>
            <button class="btn btn-primary" type="button" data-next><?= $hasIdentityFields ? 'Lanjut ke identitas →' : 'Mulai pertanyaan →' ?></button>
          </div>
        </section>

        <?php if ($hasIdentityFields): ?>
          <section class="wizard-step" data-step="identity">
            <span class="step-kicker">Identitas responden</span>
            <h2>Lengkapi identitas yang diperlukan.</h2>
            <p class="step-lead">Kolom bertanda bintang wajib diisi. Kolom lainnya boleh dikosongkan.</p>
            <?php $active_group = 'identity'; $this->load->view('public/_respondent_fields', get_defined_vars()); ?>
            <div class="wizard-actions">
              <button class="btn btn-secondary" type="button" data-back>← Kembali</button>
              <button class="btn btn-primary" type="button" data-next>Mulai pertanyaan →</button>
            </div>
          </section>
        <?php endif; ?>

        <?php $questionIndex = 0; foreach ($question_definitions as $questionId => $question): $questionIndex++; ?>
          <section class="wizard-step" data-step="question-<?= (int) $questionId ?>">
            <span class="step-kicker">Pertanyaan <?= (int) $questionIndex ?> dari <?= count($question_definitions) ?></span>
            <div class="question-number"><?= (int) $questionIndex ?></div>
            <div class="question-taxonomy">
              <span><?= html_escape($question['measurement_name']) ?></span>
              <span><?= html_escape($question['category_name']) ?></span>
            </div>
            <h2 class="question-text"><?= html_escape($question['question_text']) ?></h2>
            <p class="step-lead">Pilih jawaban yang paling sesuai dengan pengalaman Anda.</p>
            <div class="scale-options">
              <?php foreach ($question['options'] as $option): ?>
                <label class="scale-card">
                  <input type="radio" name="answer_<?= (int) $questionId ?>" value="<?= (int) $option['id'] ?>" required <?= ((string) (isset($old['answer_' . $questionId]) ? $old['answer_' . $questionId] : '') === (string) $option['id']) ? 'checked' : '' ?>>
                  <span class="scale-number"><?= html_escape(number_format((float) $option['option_value'], ((float) $option['option_value'] == (int) $option['option_value']) ? 0 : 2)) ?></span>
                  <span class="scale-label"><?= html_escape($option['option_label']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <small class="field-help question-help"><b>Cara mengisi:</b> Pilih satu jawaban yang paling menggambarkan pengalaman Anda.</small>
            <?php if (isset($validation_errors['answer_' . $questionId])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors['answer_' . $questionId])) ?></small><?php endif; ?>
            <div class="wizard-actions">
              <button class="btn btn-secondary" type="button" data-back>← Kembali</button>
              <button class="btn btn-primary" type="button" data-next><?= $questionIndex === count($question_definitions) ? 'Tinjau jawaban →' : 'Berikutnya →' ?></button>
            </div>
          </section>
        <?php endforeach; ?>

        <section class="wizard-step" data-step="finish">
          <span class="step-kicker">Langkah terakhir</span>
          <h2>Terima kasih. Sampaikan masukan terakhir Anda.</h2>
          <p class="step-lead">Saran bersifat opsional, tetapi sangat membantu perbaikan pelayanan kami.</p>
          <div class="field">
            <label for="suggestion">Saran atau masukan</label>
            <textarea id="suggestion" name="suggestion" maxlength="2000" placeholder="Tuliskan pengalaman, saran, atau hal yang perlu diperbaiki..."><?= html_escape(isset($old['suggestion']) ? $old['suggestion'] : '') ?></textarea>
            <small class="field-help"><b>Petunjuk:</b> Opsional, maksimal 2.000 karakter.</small>
          </div>
          <label class="privacy-box">
            <input type="checkbox" name="consent" value="1" required <?= !empty($old['consent']) ? 'checked' : '' ?>>
            <span>Saya menyetujui penggunaan data yang saya isi untuk evaluasi mutu dan integritas pelayanan. Data pribadi tidak ditampilkan pada statistik publik.</span>
          </label>
          <small class="field-help"><b>Petunjuk:</b> Centang persetujuan ini sebelum mengirim jawaban.</small>
          <?php if (isset($validation_errors['consent'])): ?><small class="field-error"><?= html_escape(strip_tags($validation_errors['consent'])) ?></small><?php endif; ?>
          <div class="wizard-actions">
            <button class="btn btn-secondary" type="button" data-back>← Kembali</button>
            <button class="btn btn-primary" type="submit">Kirim survei</button>
          </div>
        </section>
      </form>
    </div>
  </section>
</main>
<script src="<?= base_url('assets/ipak/js/survey.js') ?>"></script>
</body>
</html>
