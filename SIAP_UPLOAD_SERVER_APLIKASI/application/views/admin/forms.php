<?php if ($form_success): ?>
  <div class="alert question-alert-success"><?= html_escape($form_success) ?></div>
<?php endif; ?>
<?php if ($form_error): ?>
  <div class="alert alert-error"><?= html_escape($form_error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">1-3</div>
  <div>
    <strong>Satu halaman untuk mengelola survei sampai siap dibagikan</strong>
    <p>Setiap <b>survei</b> otomatis mempunyai satu <b>form utama</b>. Jika beberapa survei ingin diisi dalam satu alur, buat <b>paket survei gabungan</b> tanpa mengubah form utamanya.</p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/help') ?>">Lihat panduan lengkap</a>
</section>

<section class="panel survey-overview-panel">
  <div class="panel-head">
    <div>
      <h2>Survei yang tersedia</h2>
      <p>Hubungannya satu banding satu: <?= count($surveys) ?> survei mempunyai <?= count($primary_forms) ?> form utama. Paket gabungan dihitung terpisah.</p>
    </div>
    <div class="panel-head-actions">
      <span class="badge"><?= count($surveys) ?> survei</span>
      <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/surveys') ?>">Pengaturan survei lanjutan</a>
    </div>
  </div>
  <div class="survey-overview-grid">
    <?php foreach ($surveys as $surveyId => $survey): ?>
      <?php
      $usage = isset($survey_form_usage[$surveyId])
          ? $survey_form_usage[$surveyId]
          : ['combined_total' => 0, 'combined_active' => 0, 'combined_codes' => []];
      $primaryForm = isset($primary_form_by_survey[$surveyId])
          ? $primary_form_by_survey[$surveyId]
          : [];
      $locked = !empty($survey['is_system_locked']);
      ?>
      <article class="survey-overview-card" style="--survey-color:<?= html_escape($survey['color']) ?>">
        <div class="survey-overview-head">
          <span class="survey-overview-code"><?= html_escape($survey['survey_code']) ?></span>
          <span class="badge"><?= (int) $survey['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
        </div>
        <h3><?= html_escape($survey['survey_name']) ?></h3>
        <p><?= html_escape($survey['index_label']) ?></p>
        <div class="survey-overview-metrics">
          <div><strong><?= (int) $survey['question_count'] ?></strong><span>pertanyaan</span></div>
          <div><strong><?= $primaryForm ? '1' : '0' ?></strong><span>form utama</span></div>
        </div>
        <small>
          <?= $locked
              ? 'SKM lama dilindungi. Ikut dalam ' . (int) $usage['combined_total'] . ' paket gabungan.'
              : ((int) $usage['combined_total'] > 0
                  ? 'Ikut dalam paket: ' . html_escape(implode(', ', $usage['combined_codes']))
                  : 'Belum dimasukkan ke paket survei gabungan.') ?>
        </small>
        <div class="survey-overview-actions">
          <?php if ($primaryForm): ?>
            <a target="_blank" rel="noopener" href="<?= site_url('survey') . '?form=' . rawurlencode($primaryForm['form_code']) ?>">Buka form utama</a>
          <?php endif; ?>
          <a href="<?= site_url('admin/surveys') . '#survey-' . (int) $surveyId ?>">Atur survei</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel shortcut-panel">
  <div class="panel-head">
    <div>
      <h2>Form utama setiap survei</h2>
      <p>Satu survei selalu mempunyai satu form utama. Shortcut ini dapat dipakai untuk pengisian survei secara terpisah.</p>
    </div>
    <span class="badge"><?= count($primary_form_shortcuts) ?> form utama aktif</span>
  </div>
  <div class="survey-shortcut-grid">
    <?php foreach ($primary_form_shortcuts as $shortcut): ?>
      <?php
      $shortcutUrl = site_url('survey') . '?form=' . rawurlencode($shortcut['form_code']);
      $shortcutNeedsResi = !empty($shortcut['requires_resi']);
      $shortcutSurveyCodes = [];
      foreach ($shortcut['shortcut_surveys'] as $shortcutSurvey) {
          $shortcutSurveyCodes[] = $shortcutSurvey['code'];
      }
      ?>
      <a class="survey-shortcut-card" href="<?= html_escape($shortcutUrl) ?>" target="_blank" rel="noopener" style="--shortcut-color:<?= html_escape($shortcut['shortcut_color']) ?>">
        <span class="shortcut-code"><?= html_escape($shortcut['form_code']) ?></span>
        <strong><?= html_escape($shortcut['form_name']) ?></strong>
        <small>Survei: <?= html_escape(implode(' + ', $shortcutSurveyCodes)) ?></small>
        <small><?= $shortcutNeedsResi ? 'Memerlukan nomor resi karena memuat SKM' : 'Dapat diisi langsung tanpa nomor resi' ?></small>
        <code>?form=<?= html_escape($shortcut['form_code']) ?></code>
        <b>Buka form →</b>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel combined-package-panel">
  <div class="panel-head">
    <div>
      <h2>Paket survei gabungan</h2>
      <p>Paket hanya menyatukan alur pengisian. Hasil setiap survei tetap disimpan dan dihitung secara terpisah.</p>
    </div>
    <span class="badge"><?= count($combined_form_shortcuts) ?> paket aktif</span>
  </div>

  <?php if ($is_superadmin): ?>
    <details class="package-creator">
      <summary>
        <span class="package-creator-icon">+</span>
        <span><strong>Buat paket survei gabungan</strong><small>Pilih minimal dua survei yang akan diisi melalui satu shortcut.</small></span>
        <b>Buka pengaturan</b>
      </summary>
      <div class="package-creator-body">
        <form method="post" action="<?= site_url('admin/forms') ?>">
          <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
          <input type="hidden" name="form_id" value="0">
          <input type="hidden" name="form_kind" value="combined">
          <input type="hidden" name="is_active" value="1">
          <input type="hidden" name="is_public_listed" value="1">
          <div class="question-config-grid">
            <div class="field">
              <label>Kode paket</label>
              <input name="form_code" maxlength="30" placeholder="Contoh: SKM-IPAK-2026" required>
              <small class="field-help"><b>Petunjuk:</b> Dipakai pada URL shortcut, gunakan huruf, angka, atau tanda hubung tanpa spasi.</small>
            </div>
            <div class="field">
              <label>Nama paket</label>
              <input name="form_name" maxlength="150" placeholder="Contoh: Paket SKM dan IPAK" required>
              <small class="field-help"><b>Petunjuk:</b> Nama ini hanya untuk membedakan paket pengisian.</small>
            </div>
            <div class="field full">
              <label>Deskripsi paket</label>
              <textarea name="description" maxlength="2000" placeholder="Jelaskan kapan paket gabungan ini digunakan."></textarea>
            </div>
          </div>
          <div class="field-label">Pilih minimal dua survei</div>
          <div class="choice-grid survey-picker">
            <?php foreach ($surveys as $surveyId => $survey): ?>
              <label class="choice-card <?= !empty($survey['is_system_locked']) ? 'choice-card-locked' : '' ?>">
                <input type="checkbox" name="survey_ids[]" value="<?= (int) $surveyId ?>">
                <span>
                  <b><?= html_escape($survey['survey_name']) ?></b><br>
                  <small><?= html_escape($survey['survey_code']) ?><?= !empty($survey['is_system_locked']) ? ' · membawa aturan resi SKM' : '' ?></small>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="form-save-actions">
            <button class="btn btn-primary" type="submit">Simpan paket gabungan</button>
            <span class="field-help">Setelah dibuat, data responden dapat disesuaikan pada pengaturan paket di bawah.</span>
          </div>
        </form>
      </div>
    </details>
  <?php endif; ?>

  <?php if (!$combined_form_shortcuts): ?>
    <div class="empty-state compact-empty-state">Belum ada paket gabungan aktif. Form utama setiap survei tetap dapat digunakan.</div>
  <?php else: ?>
    <div class="survey-shortcut-grid">
      <?php foreach ($combined_form_shortcuts as $shortcut): ?>
        <?php
        $shortcutUrl = site_url('survey') . '?form=' . rawurlencode($shortcut['form_code']);
        $shortcutNeedsResi = !empty($shortcut['requires_resi']);
        $shortcutSurveyCodes = [];
        foreach ($shortcut['shortcut_surveys'] as $shortcutSurvey) {
            $shortcutSurveyCodes[] = $shortcutSurvey['code'];
        }
        ?>
        <a class="survey-shortcut-card combined-shortcut-card" href="<?= html_escape($shortcutUrl) ?>" target="_blank" rel="noopener" style="--shortcut-color:<?= html_escape($shortcut['shortcut_color']) ?>">
          <span class="shortcut-code"><?= html_escape($shortcut['form_code']) ?></span>
          <strong><?= html_escape($shortcut['form_name']) ?></strong>
          <small>Paket gabungan: <?= html_escape(implode(' + ', $shortcutSurveyCodes)) ?></small>
          <small><?= $shortcutNeedsResi ? 'Memerlukan nomor resi karena memuat SKM' : 'Dapat diisi langsung tanpa nomor resi' ?></small>
          <code>?form=<?= html_escape($shortcut['form_code']) ?></code>
          <b>Buka paket →</b>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php
$formGroups = [
    [
        'kind' => 'primary',
        'title' => 'Pengaturan form utama',
        'description' => 'Setiap baris terhubung permanen ke satu survei. Atur nama, data responden, status, dan shortcutnya.',
        'forms' => $primary_forms,
        'badge' => count($primary_forms) . ' form utama',
    ],
    [
        'kind' => 'combined',
        'title' => 'Pengaturan paket gabungan',
        'description' => 'Paket berisi minimal dua survei, tetapi tidak dihitung sebagai form utama tambahan.',
        'forms' => $combined_forms,
        'badge' => count($combined_forms) . ' paket',
    ],
];
?>
<?php foreach ($formGroups as $formGroup): ?>
<section class="panel">
  <div class="panel-head">
    <div>
      <h2><?= html_escape($formGroup['title']) ?></h2>
      <p><?= html_escape($formGroup['description']) ?></p>
    </div>
    <span class="badge"><?= html_escape($formGroup['badge']) ?></span>
  </div>

  <?php if (!$is_superadmin): ?>
    <div class="alert question-readonly-note">Anda dapat melihat dan membuka form. Perubahan hanya dapat dilakukan oleh superadmin.</div>
  <?php endif; ?>

  <div class="question-admin-list">
    <?php if (!$formGroup['forms']): ?>
      <div class="empty-state compact-empty-state">Belum ada data pada bagian ini.</div>
    <?php endif; ?>
    <?php foreach ($formGroup['forms'] as $form): ?>
      <details class="question-editor">
        <summary>
          <span class="question-editor-code"><?= html_escape($form['form_code']) ?></span>
          <span class="question-editor-copy">
            <strong><?= html_escape($form['form_name']) ?></strong>
            <small><?= $formGroup['kind'] === 'primary' ? 'Form utama · 1 survei' : 'Paket gabungan · ' . (int) $form['survey_count'] . ' survei' ?> · <?= !empty($form['requires_resi']) ? 'wajib resi SKM' : 'tanpa resi' ?></small>
          </span>
          <span class="question-editor-meta"><?= (int) $form['is_default'] === 1 ? 'Form bawaan' : 'Form alternatif' ?></span>
          <span class="badge"><?= (int) $form['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
          <span class="badge"><?= !empty($form['is_public_listed']) ? 'Tampil di front office' : 'Disembunyikan dari front office' ?></span>
        </summary>
        <div class="question-editor-body">
          <?php if (!empty($form['requires_resi'])): ?>
            <div class="locked-rule-box">
              <strong>Nomor resi SKM aktif permanen pada form ini.</strong>
              <span>Karena form memuat SKM, responden harus memakai nomor resi izin yang sudah terbit. Aturan ini tidak dapat dinonaktifkan.</span>
            </div>
          <?php else: ?>
            <div class="direct-form-box">
              <strong>Form dapat diisi langsung.</strong>
              <span>Tidak ada nomor resi. Identitas yang diminta mengikuti pengaturan data responden di bawah.</span>
            </div>
          <?php endif; ?>

          <?php if ($is_superadmin): ?>
            <form method="post" action="<?= site_url('admin/forms') ?>">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
              <input type="hidden" name="form_kind" value="<?= html_escape($formGroup['kind']) ?>">

              <div class="form-section-heading">
                <span>Bagian 1</span>
                <div><h3>Informasi form</h3><p>Nama dan kode untuk membedakan form.</p></div>
              </div>
              <div class="question-config-grid">
                <div class="field"><label>Kode form</label><input name="form_code" maxlength="30" value="<?= html_escape($form['form_code']) ?>" required><small class="field-help"><b>Petunjuk:</b> Digunakan pada URL <code>?form=KODE</code>, tanpa spasi.</small></div>
                <div class="field"><label>Nama form</label><input name="form_name" maxlength="150" value="<?= html_escape($form['form_name']) ?>" required><small class="field-help"><b>Petunjuk:</b> Nama yang dilihat responden.</small></div>
                <label class="question-active-check"><input type="checkbox" name="is_default" value="1" <?= (int) $form['is_default'] === 1 ? 'checked' : '' ?>> Jadikan form bawaan</label>
                <label class="question-active-check"><input type="checkbox" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>> Form aktif</label>
                <input type="hidden" name="is_public_listed" value="0">
                <label class="question-active-check">
                  <input type="checkbox" name="is_public_listed" value="1" <?= !empty($form['is_public_listed']) ? 'checked' : '' ?>>
                  Tampilkan pada pilihan formulir di front office
                  <small class="field-help">Jika dimatikan, form tidak muncul di dashboard dan katalog publik, tetapi URL shortcut tetap dapat digunakan.</small>
                </label>
                <div class="field full"><label>Deskripsi</label><textarea name="description" maxlength="2000"><?= html_escape($form['description']) ?></textarea><small class="field-help"><b>Petunjuk:</b> Jelaskan tujuan form kepada pengelola.</small></div>
              </div>

              <div class="form-section-heading">
                <span>Bagian 2</span>
                <div>
                  <h3><?= $formGroup['kind'] === 'primary' ? 'Survei pemilik form utama' : 'Survei dalam paket gabungan' ?></h3>
                  <p><?= $formGroup['kind'] === 'primary' ? 'Hubungan satu survei dan satu form utama dikunci agar jumlahnya selalu sama.' : 'Pilih minimal dua survei untuk satu alur pengisian.' ?></p>
                </div>
              </div>
              <?php if ($formGroup['kind'] === 'primary'): ?>
                <?php
                $primarySurveyId = (int) $form['primary_survey_id'];
                $primarySurvey = isset($surveys[$primarySurveyId]) ? $surveys[$primarySurveyId] : [];
                ?>
                <input type="hidden" name="survey_ids[]" value="<?= $primarySurveyId ?>">
                <div class="primary-survey-lock">
                  <span class="survey-overview-code" style="--survey-color:<?= html_escape(isset($primarySurvey['color']) ? $primarySurvey['color'] : '#3049d8') ?>"><?= html_escape(isset($primarySurvey['survey_code']) ? $primarySurvey['survey_code'] : '-') ?></span>
                  <div>
                    <strong><?= html_escape(isset($primarySurvey['survey_name']) ? $primarySurvey['survey_name'] : 'Survei') ?></strong>
                    <small>Form utama tidak dapat digabungkan. Buat paket gabungan jika responden harus mengisi beberapa survei sekaligus.</small>
                  </div>
                </div>
              <?php else: ?>
                <div class="choice-grid survey-picker">
                  <?php foreach ($surveys as $surveyId => $survey): ?>
                    <label class="choice-card <?= !empty($survey['is_system_locked']) ? 'choice-card-locked' : '' ?>">
                      <input type="checkbox" name="survey_ids[]" value="<?= (int) $surveyId ?>" <?= in_array((int) $surveyId, $form['survey_ids'], true) ? 'checked' : '' ?>>
                      <span>
                        <b><?= html_escape($survey['survey_name']) ?></b><br>
                        <small><?= html_escape($survey['survey_code']) ?><?= !empty($survey['is_system_locked']) ? ' · membawa aturan resi' : '' ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="form-section-heading">
                <span>Bagian 3</span>
                <div>
                  <h3>Atur data responden</h3>
                  <p>
                    <?= $formGroup['kind'] === 'combined'
                        ? 'Data responden dari semua survei dalam paket digabungkan otomatis. Kolom yang sama hanya tampil satu kali, sedangkan status wajib dari salah satu survei tetap wajib.'
                        : 'Pilih “Disembunyikan” jika data tidak diperlukan. Label dan petunjuk dapat disesuaikan dengan kebutuhan.' ?>
                  </p>
                </div>
              </div>
              <div class="respondent-field-list">
                <?php foreach ($field_definitions as $fieldKey => $definition): ?>
                  <?php
                  $setting = isset($form['respondent_fields'][$fieldKey])
                      ? $form['respondent_fields'][$fieldKey]
                      : [
                          'field_mode' => $fieldKey === 'email' ? 'required' : 'hidden',
                          'field_label' => $definition['label'],
                          'help_text' => $definition['help_text'],
                      ];
                  ?>
                  <div class="respondent-field-row">
                    <div class="respondent-field-title">
                      <strong><?= html_escape($definition['label']) ?></strong>
                      <small>Nama sistem: <?= html_escape($fieldKey) ?></small>
                    </div>
                    <div class="field">
                      <label>Status kolom</label>
                      <select name="respondent_fields[<?= html_escape($fieldKey) ?>][mode]">
                        <option value="hidden" <?= $setting['field_mode'] === 'hidden' ? 'selected' : '' ?>>Disembunyikan</option>
                        <option value="optional" <?= $setting['field_mode'] === 'optional' ? 'selected' : '' ?>>Ditampilkan, boleh kosong</option>
                        <option value="required" <?= $setting['field_mode'] === 'required' ? 'selected' : '' ?>>Wajib diisi</option>
                      </select>
                    </div>
                    <div class="field">
                      <label>Label yang dilihat responden</label>
                      <input name="respondent_fields[<?= html_escape($fieldKey) ?>][label]" maxlength="100" value="<?= html_escape($setting['field_label']) ?>">
                    </div>
                    <div class="field respondent-help-input">
                      <label>Petunjuk pengisian</label>
                      <input name="respondent_fields[<?= html_escape($fieldKey) ?>][help_text]" maxlength="255" value="<?= html_escape($setting['help_text']) ?>">
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php foreach ($form['respondent_fields'] as $fieldKey => $setting): ?>
                  <?php if (!empty($setting['is_system'])) continue; ?>
                  <div class="respondent-field-row custom-respondent-field">
                    <div class="respondent-field-title">
                      <strong><?= html_escape($setting['field_label']) ?></strong>
                      <small>Kolom buatan · <?= $setting['field_group'] === 'access' ? 'Input awal' : 'Identitas' ?> · <?= html_escape($setting['field_type']) ?></small>
                    </div>
                    <div class="field">
                      <label>Status kolom</label>
                      <select name="respondent_fields[<?= html_escape($fieldKey) ?>][mode]">
                        <option value="hidden" <?= $setting['field_mode'] === 'hidden' ? 'selected' : '' ?>>Disembunyikan</option>
                        <option value="optional" <?= $setting['field_mode'] === 'optional' ? 'selected' : '' ?>>Ditampilkan, boleh kosong</option>
                        <option value="required" <?= $setting['field_mode'] === 'required' ? 'selected' : '' ?>>Wajib diisi</option>
                      </select>
                    </div>
                    <div class="field">
                      <label>Label yang dilihat responden</label>
                      <input name="respondent_fields[<?= html_escape($fieldKey) ?>][label]" maxlength="100" value="<?= html_escape($setting['field_label']) ?>">
                    </div>
                    <div class="field respondent-help-input">
                      <label>Petunjuk pengisian</label>
                      <input name="respondent_fields[<?= html_escape($fieldKey) ?>][help_text]" maxlength="255" value="<?= html_escape($setting['help_text']) ?>">
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="form-save-actions">
                <button class="btn btn-primary" type="submit"><?= $formGroup['kind'] === 'primary' ? 'Simpan form utama' : 'Simpan paket gabungan' ?></button>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= site_url('survey') . '?form=' . rawurlencode($form['form_code']) ?>">Pratinjau form</a>
              </div>
            </form>
          <?php else: ?>
            <p><?= html_escape($form['description']) ?></p>
            <a class="btn btn-secondary btn-sm" target="_blank" rel="noopener" href="<?= site_url('survey') . '?form=' . rawurlencode($form['form_code']) ?>">Buka form</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<?php if ($is_superadmin): ?>
  <section class="builder-cta">
    <div class="guide-number">＋</div>
    <div>
      <strong>Buat survei baru beserta form utamanya</strong>
      <p>Ikuti tiga langkah. Setelah survei selesai dibuat, sistem otomatis membuat tepat satu form utama dan satu shortcut.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('admin/forms/create') ?>">Buat survei baru →</a>
  </section>
<?php endif; ?>
