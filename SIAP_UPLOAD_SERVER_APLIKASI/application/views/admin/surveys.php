<?php if ($survey_success): ?>
  <div class="alert question-alert-success"><?= html_escape($survey_success) ?></div>
<?php endif; ?>
<?php if ($survey_error): ?>
  <div class="alert alert-error"><?= html_escape($survey_error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">i</div>
  <div>
    <strong>Halaman lanjutan untuk mengubah survei yang sudah ada</strong>
    <p>Halaman utama Survei, Form & Shortcut tetap menjadi pusat pengelolaan. Gunakan halaman ini hanya untuk mengubah nama, penilaian, warna, dan susunan pertanyaan survei.</p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/forms') ?>">Kembali ke halaman utama survei</a>
</section>

<section class="panel" style="margin-top:18px">
  <div class="panel-head">
    <div>
      <h2>Daftar survei</h2>
      <p>Buka satu baris untuk melihat atau mengubah survei yang sudah ada. Pembuatan baru hanya tersedia melalui wizard lengkap.</p>
    </div>
    <span class="badge"><?= count($surveys) ?> survei</span>
  </div>

  <?php if (!$is_superadmin): ?>
    <div class="alert question-readonly-note">Anda dapat melihat pengaturan. Perubahan hanya dapat dilakukan oleh superadmin.</div>
  <?php endif; ?>

  <div class="question-admin-list">
    <?php foreach ($surveys as $surveyId => $survey): ?>
      <?php $locked = !empty($survey['is_system_locked']); ?>
      <details id="survey-<?= (int) $surveyId ?>" class="question-editor">
        <summary>
          <span class="question-editor-code" style="background:<?= html_escape($survey['color']) ?>"><?= html_escape($survey['survey_code']) ?></span>
          <span class="question-editor-copy">
            <strong><?= html_escape($survey['survey_name']) ?><?= $locked ? ' · Survei sistem' : '' ?></strong>
            <small><?= html_escape($survey['index_label']) ?> · <?= (int) $survey['question_count'] ?> pertanyaan</small>
          </span>
          <span class="question-editor-meta"><?= number_format((int) $survey['response_count']) ?> hasil tersimpan</span>
          <span class="badge"><?= (int) $survey['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
        </summary>
        <div class="question-editor-body">
          <?php if ($locked): ?>
            <div class="locked-rule-box">
              <strong>SKM selalu aktif dan dilindungi.</strong>
              <span>Survei ini menggunakan profil SKM-LEGACY-10, tidak dapat dinonaktifkan atau dihapus, dan maksimal memiliki 10 pertanyaan. Form gabungan tetap menyimpan hasil SKM pada baris tersendiri.</span>
            </div>
          <?php endif; ?>

          <div class="field" style="margin-bottom:16px">
            <label>Kode unik permanen</label>
            <input value="<?= html_escape(isset($survey['kode_unik']) ? $survey['kode_unik'] : '-') ?>" readonly>
            <small class="field-help"><b>Informasi:</b> UUID ini dibuat otomatis, tidak dapat diedit, dan dijamin tidak digunakan oleh survei lain.</small>
          </div>
          <div class="field" style="margin-bottom:16px">
            <label>Versi dan profil penyimpanan</label>
            <input value="<?= html_escape((isset($survey['survey_version']) ? $survey['survey_version'] : '1') . ' · ' . (isset($survey['storage_profile']) ? $survey['storage_profile'] : 'FLEX')) ?>" readonly>
            <small class="field-help"><b>Informasi:</b> SKM lama memakai keluaran tetap 10 posisi; survei baru menyimpan seluruh pertanyaannya secara fleksibel.</small>
          </div>

          <?php if ($is_superadmin): ?>
            <form method="post" action="<?= site_url('admin/surveys') ?>">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <input type="hidden" name="entity" value="survey">
              <input type="hidden" name="survey_id" value="<?= (int) $surveyId ?>">
              <div class="question-config-grid">
                <div class="field">
                  <label>Kode survei</label>
                  <input name="survey_code" maxlength="30" value="<?= html_escape($survey['survey_code']) ?>" required <?= $locked ? 'readonly' : '' ?>>
                  <small class="field-help"><b>Petunjuk:</b> Kode singkat yang mudah dibaca. Identitas teknis permanen menggunakan UUID di atas.</small>
                </div>
                <div class="field">
                  <label>Nama survei</label>
                  <input name="survey_name" maxlength="150" value="<?= html_escape($survey['survey_name']) ?>" required>
                  <small class="field-help"><b>Petunjuk:</b> Gunakan nama yang mudah dipahami pengelola.</small>
                </div>
                <div class="field">
                  <label>Label nilai di dashboard</label>
                  <input name="index_label" maxlength="60" value="<?= html_escape($survey['index_label']) ?>" required>
                  <small class="field-help"><b>Petunjuk:</b> Contoh: “Nilai SKM” atau “Indeks Kepuasan”.</small>
                </div>
                <div class="field">
                  <label>Warna grafik</label>
                  <input name="color" type="color" value="<?= html_escape($survey['color']) ?>" required>
                  <small class="field-help"><b>Petunjuk:</b> Warna pembeda pada ringkasan dan grafik.</small>
                </div>
                <?php if ($locked): ?>
                  <input type="hidden" name="is_active" value="1">
                  <div class="locked-switch">Aktif permanen · tidak dapat dinonaktifkan</div>
                <?php else: ?>
                  <label class="question-active-check"><input type="checkbox" name="is_active" value="1" <?= (int) $survey['is_active'] === 1 ? 'checked' : '' ?>> Survei aktif</label>
                <?php endif; ?>
                <div class="field full">
                  <label>Deskripsi</label>
                  <textarea name="description" maxlength="2000"><?= html_escape($survey['description']) ?></textarea>
                  <small class="field-help"><b>Petunjuk:</b> Jelaskan tujuan dan aspek yang diukur.</small>
                </div>
              </div>

              <div class="option-editor-head">
                <div>
                  <h3>Pertanyaan dalam survei</h3>
                  <p>Pertanyaan yang sudah digunakan survei lain disembunyikan. Pertanyaan yang sudah dipakai survei ini tetap terlihat.</p>
                </div>
              </div>
              <div class="choice-grid survey-question-picker">
                <?php foreach ($questions as $questionId => $question): ?>
                  <label class="choice-card">
                    <input type="checkbox" name="question_ids[]" value="<?= (int) $questionId ?>" <?= in_array((int) $questionId, $survey['question_ids'], true) ? 'checked' : '' ?>>
                    <span><b><?= html_escape($question['question_code']) ?></b><br><small><?= html_escape($question['question_text']) ?></small></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-primary btn-sm" type="submit">Simpan perubahan survei</button>
            </form>
          <?php else: ?>
            <p><?= html_escape($survey['description']) ?></p>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<script>
(function () {
  if (!window.location.hash) {
    return;
  }
  var selectedSurvey = document.querySelector(window.location.hash);
  if (selectedSurvey && selectedSurvey.tagName === 'DETAILS') {
    selectedSurvey.open = true;
    selectedSurvey.scrollIntoView({behavior: 'smooth', block: 'start'});
  }
}());
</script>
