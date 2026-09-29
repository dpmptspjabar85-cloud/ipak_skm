<?php
$wizardMode = isset($mode) ? $mode : 'create';
$isEditMode = $wizardMode === 'edit';
$wizardAction = $isEditMode
    ? site_url('admin/forms/edit/' . (int) $form_id)
    : site_url('admin/forms/create');
$wizardTitle = $isEditMode ? 'Edit Form Survei' : 'Buat Form Survei';
$wizardBackUrl = $isEditMode
    ? site_url('admin/forms')
    : site_url('admin/forms');
$submitLabel = $isEditMode ? 'Simpan perubahan form' : 'Buat form survei';
$heroTitle = $isEditMode ? 'Edit form survei yang sudah ada.' : 'Buat form survei dalam tiga langkah.';
$heroHelp = $isEditMode
    ? 'Form ini sudah terhubung dengan satu survei. Anda dapat memperbarui pertanyaan, isian responden, dan informasi form di bawah.'
    : 'Isi dari langkah pertama sampai terakhir. Form baru disimpan setelah Anda menekan tombol “Buat form survei”.';
$heroBtnLabel = $isEditMode ? 'Kembali ke daftar form' : 'Kembali ke daftar form';
$initialBtnLabel = $isEditMode ? 'Lanjut ke identitas →' : 'Lanjut ke identitas →';
$identityBtnLabel = $isEditMode ? 'Lanjut ke pertanyaan →' : 'Lanjut ke pertanyaan →';
$initialFieldsChecked = ['email', 'phone', 'identity_number'];
$oldInitialFields = isset($old['initial_fields']) && is_array($old['initial_fields']) ? $old['initial_fields'] : (in_array('email', $initialFieldsChecked, true) ? ['email'] : []);
$oldIdentityFields = isset($old['identity_fields']) && is_array($old['identity_fields']) ? $old['identity_fields'] : [];
$oldIdentityOptions = isset($old['identity_options']) && is_array($old['identity_options']) ? $old['identity_options'] : [];
$oldKbliConfig = isset($old['identity_kbli']) && is_array($old['identity_kbli']) ? $old['identity_kbli'] : [];
$oldInitialCustom = isset($old['initial_custom']) && is_array($old['initial_custom']) ? $old['initial_custom'] : [];
$oldIdentityCustom = isset($old['identity_custom']) && is_array($old['identity_custom']) ? $old['identity_custom'] : [];
$oldQuestions = isset($old['new_questions']) && is_array($old['new_questions']) ? $old['new_questions'] : [[]];
$oldQuestionIds = isset($old['question_ids']) && is_array($old['question_ids']) ? array_map('intval', $old['question_ids']) : [];
$oldPublicListed = $old ? !empty($old['is_public_listed']) : true;
$oldValue = function ($key, $fallback = '') use ($old) {
    return isset($old[$key]) ? $old[$key] : $fallback;
};
$customOptions = function ($field) {
  $options = isset($field['options']) ? $field['options'] : [];
  if (is_string($options)) {
    $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $options))));
  }
  if (!is_array($options)) {
    return [];
  }
  $normalized = [];
  foreach ($options as $index => $option) {
    if (is_array($option)) {
      $normalized[] = [
        'label' => isset($option['label']) ? $option['label'] : '',
        'value' => isset($option['value']) ? $option['value'] : $index + 1,
      ];
    } else {
      $normalized[] = ['label' => $option, 'value' => $index + 1];
    }
  }
  return $normalized;
};
$choiceOptions = isset($choice_options) ? $choice_options : [
    'gender' => [1 => 'Laki-laki', 2 => 'Perempuan'],
    'education' => [],
    'job' => [],
    'service' => [],
];
?>

<?php if ($wizard_error): ?>
  <div class="alert alert-error"><?= html_escape($wizard_error) ?></div>
<?php endif; ?>

<section class="builder-hero">
  <div>
    <span class="eyebrow">Panduan pengelolaan form</span>
    <h2><?= $isEditMode ? 'Edit form survei dalam tiga langkah.' : 'Buat form survei dalam tiga langkah.' ?></h2>
    <p><?= $isEditMode
        ? 'Form ini sudah terhubung dengan satu survei. Perbarui pertanyaan, isian responden, dan informasi form di bawah.'
        : 'Isi dari langkah pertama sampai terakhir. Form baru disimpan setelah Anda menekan tombol “Buat form survei”.' ?></p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= $wizardBackUrl ?>">Kembali ke daftar form</a>
</section>

<div class="builder-progress" aria-label="Tahapan pembuatan form">
  <button type="button" class="builder-progress-item is-active" data-step-jump="0">
    <span>1</span><b>Input awal wajib</b><small>Email, telepon, nomor induk</small>
  </button>
  <button type="button" class="builder-progress-item" data-step-jump="1">
    <span>2</span><b>Identitas responden</b><small>Nama, alamat, dan lainnya</small>
  </button>
  <button type="button" class="builder-progress-item" data-step-jump="2">
    <span>3</span><b>Pertanyaan survei</b><small>Pilih atau buat langsung</small>
  </button>
</div>

<form id="form-builder" method="post" action="<?= $wizardAction ?>" novalidate>
  <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
  <?php if ($isEditMode): ?>
    <input type="hidden" name="form_id" value="<?= (int) $form_id ?>">
  <?php endif; ?>

  <section class="panel builder-step is-active" data-builder-step="0">
    <div class="builder-step-head">
      <span class="builder-step-number">1</span>
      <div>
        <h2>Tentukan input pertama yang wajib diisi</h2>
        <p>Pilih minimal satu. Semua input pada langkah ini akan tampil lebih dahulu dan wajib diisi oleh responden.</p>
      </div>
    </div>

    <div class="builder-choice-grid">
      <?php foreach (['email', 'phone', 'identity_number'] as $fieldKey): ?>
        <?php $definition = $field_definitions[$fieldKey]; ?>
        <label class="builder-choice">
          <input type="checkbox" name="initial_fields[]" value="<?= html_escape($fieldKey) ?>" <?= in_array($fieldKey, $oldInitialFields, true) ? 'checked' : '' ?>>
          <span>
            <b><?= html_escape($definition['label']) ?></b>
            <small><?= html_escape($definition['help_text']) ?></small>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="builder-subsection">
      <div class="builder-subsection-head">
        <div>
          <h3>Butuh input awal lainnya?</h3>
          <p>Contoh: nomor kartu anggota, kode undangan, nomor pasien, atau kode siswa.</p>
        </div>
        <button class="btn btn-secondary btn-sm" type="button" data-add-custom="initial">＋ Tambah input awal</button>
      </div>
      <div id="initial-custom-list" class="custom-field-builder-list">
        <?php foreach ($oldInitialCustom as $index => $field): ?>
          <div class="custom-field-builder-row">
            <div class="field"><label>Nama input</label><input name="initial_custom[<?= (int) $index ?>][label]" maxlength="100" value="<?= html_escape(isset($field['label']) ? $field['label'] : '') ?>" placeholder="Contoh: Nomor kartu anggota"></div>
            <div class="field"><label>Jenis input</label><select name="initial_custom[<?= (int) $index ?>][type]">
              <?php foreach (['text' => 'Teks', 'email' => 'Email', 'tel' => 'Nomor telepon', 'number' => 'Angka', 'textarea' => 'Teks panjang', 'select' => 'Daftar pilihan'] as $type => $label): ?>
                <option value="<?= $type ?>" <?= (isset($field['type']) ? $field['type'] : 'text') === $type ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select></div>
            <div class="field"><label>Petunjuk</label><input name="initial_custom[<?= (int) $index ?>][help_text]" maxlength="255" value="<?= html_escape(isset($field['help_text']) ? $field['help_text'] : '') ?>" placeholder="Jelaskan cara mengisinya"></div>
            <div class="custom-options-field" hidden>
              <div class="choice-value-heading"><strong>Pilihan dropdown</strong><small>Pilih value angka atau gunakan teks label sebagai value.</small></div>
              <label class="choice-value-mode">Value pilihan<select name="initial_custom[<?= (int) $index ?>][value_mode]" data-choice-value-mode><option value="numeric" <?= isset($field['value_mode']) && $field['value_mode'] === 'label' ? '' : 'selected' ?>>Angka</option><option value="label" <?= isset($field['value_mode']) && $field['value_mode'] === 'label' ? 'selected' : '' ?>>Sama dengan label</option></select></label>
              <div class="choice-value-rows">
                <?php $fieldOptions = $customOptions($field); ?>
                <?php foreach ($fieldOptions as $optionIndex => $option): ?>
                  <div class="choice-value-row custom-option-row">
                    <label>Label<input name="initial_custom[<?= (int) $index ?>][options][<?= (int) $optionIndex ?>][label]" maxlength="100" value="<?= html_escape($option['label']) ?>" placeholder="Contoh: SD"></label>
                    <label class="choice-value-input-label">Value<input name="initial_custom[<?= (int) $index ?>][options][<?= (int) $optionIndex ?>][value]" type="text" value="<?= html_escape($option['value']) ?>" placeholder="Contoh: 1"></label>
                    <button class="custom-remove-button" type="button" data-remove-choice>Hapus</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-secondary btn-sm" type="button" data-add-custom-choice>Tambah pilihan</button>
            </div>
            <button class="custom-remove-button" type="button" data-remove-row aria-label="Hapus input">Hapus</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="builder-actions">
      <span></span>
      <button class="btn btn-primary" type="button" data-builder-next><?= $initialBtnLabel ?></button>
    </div>
  </section>

  <section class="panel builder-step" data-builder-step="1">
    <div class="builder-step-head">
      <span class="builder-step-number">2</span>
      <div>
        <h2>Tentukan identitas responden</h2>
        <p>Setiap kolom dapat dibuat wajib, opsional, atau tidak ditampilkan. Tambahkan kolom baru jika pilihan belum tersedia.</p>
      </div>
    </div>

    <div class="identity-setting-list">
      <?php foreach (['name', 'address', 'gender', 'age', 'education', 'job', 'service', 'kbli'] as $fieldKey): ?>
        <?php
        $definition = $field_definitions[$fieldKey];
        $selectedMode = isset($oldIdentityFields[$fieldKey]) ? $oldIdentityFields[$fieldKey] : 'hidden';
        ?>
        <div class="identity-setting-row">
          <div><strong><?= html_escape($definition['label']) ?></strong><small><?= html_escape($definition['help_text']) ?></small></div>
          <label>
            <span>Status kolom</span>
            <select name="identity_fields[<?= html_escape($fieldKey) ?>]">
              <option value="hidden" <?= $selectedMode === 'hidden' ? 'selected' : '' ?>>Tidak ditampilkan</option>
              <option value="optional" <?= $selectedMode === 'optional' ? 'selected' : '' ?>>Ditampilkan, boleh kosong</option>
              <option value="required" <?= $selectedMode === 'required' ? 'selected' : '' ?>>Wajib diisi</option>
            </select>
          </label>
        </div>
        <?php if (in_array($fieldKey, ['gender', 'education', 'job', 'service'], true)): ?>
          <?php
          $fieldOptions = isset($oldIdentityOptions[$fieldKey]) && is_array($oldIdentityOptions[$fieldKey])
            ? $oldIdentityOptions[$fieldKey]
            : [];
          if (!$fieldOptions) {
            foreach ($choiceOptions[$fieldKey] as $optionValue => $optionLabel) {
                $fieldOptions[] = ['label' => $optionLabel, 'value' => $optionValue];
            }
          }
          ?>
          <div class="choice-value-editor" data-choice-list="identity_options[<?= html_escape($fieldKey) ?>]">
            <div class="choice-value-heading"><strong>Pilihan dan value angka</strong><small>Ubah label sesuai kebutuhan. Value memakai kode angka kategori yang tersedia agar grafik tetap sesuai.</small></div>
            <div class="choice-value-rows">
              <?php foreach ($fieldOptions as $optionIndex => $option): ?>
                <div class="choice-value-row">
                  <label>Label<input name="identity_options[<?= html_escape($fieldKey) ?>][<?= (int) $optionIndex ?>][label]" maxlength="100" value="<?= html_escape(isset($option['label']) ? $option['label'] : '') ?>" placeholder="Contoh: SD"></label>
                  <label>Value angka<input name="identity_options[<?= html_escape($fieldKey) ?>][<?= (int) $optionIndex ?>][value]" type="number" step="1" value="<?= html_escape(isset($option['value']) ? $option['value'] : '') ?>" placeholder="Contoh: 1"></label>
                  <button class="custom-remove-button" type="button" data-remove-choice aria-label="Hapus pilihan">Hapus</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-secondary btn-sm" type="button" data-add-choice>Tambah pilihan</button>
          </div>
        <?php endif; ?>
        <?php if ($fieldKey === 'kbli'): ?>
          <?php
          $selectedKbliCodeField = isset($oldKbliConfig['code_field']) ? $oldKbliConfig['code_field'] : 'kode_gabungan';
          $selectedKbliColumns = isset($oldKbliConfig['display_columns']) && is_array($oldKbliConfig['display_columns'])
            ? $oldKbliConfig['display_columns']
            : ['kode_gabungan', 'sektor_bps', 'judul'];
          ?>
          <div class="choice-value-editor kbli-identity-config">
            <div class="choice-value-heading"><strong>Pengaturan pilihan KBLI</strong><small>Responden memilih satu baris katalog. Nilai formulir tetap terhubung ke record KBLI.</small></div>
            <label class="choice-value-mode">Kode utama<select name="identity_kbli[code_field]"><option value="kode_gabungan" <?= $selectedKbliCodeField === 'kode_gabungan' ? 'selected' : '' ?>>Kode Gabungan</option><option value="kode" <?= $selectedKbliCodeField === 'kode' ? 'selected' : '' ?>>Kode</option></select></label>
            <div class="kbli-display-columns"><strong>Informasi yang ditampilkan ke responden</strong><small>Pilih satu atau lebih. Kode utama tetap ditampilkan sebagai identitas opsi.</small>
              <input type="search" class="choice-search" placeholder="Cari kolom KBLI…" data-filter=".choice-card"><div class="choice-grid">
                <?php foreach ($kbli_display_columns as $columnKey => $columnLabel): ?>
                  <label class="choice-card"><input type="checkbox" name="identity_kbli[display_columns][]" value="<?= html_escape($columnKey) ?>" <?= in_array($columnKey, $selectedKbliColumns, true) ? 'checked' : '' ?>><span><?= html_escape($columnLabel) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="builder-subsection">
      <div class="builder-subsection-head">
        <div>
          <h3>Tambahkan identitas lain</h3>
          <p>Contoh: nama orang tua, kecamatan, instansi, kelas, jabatan, atau kebutuhan khusus lainnya.</p>
        </div>
        <button class="btn btn-secondary btn-sm" type="button" data-add-custom="identity">＋ Tambah identitas</button>
      </div>
      <div id="identity-custom-list" class="custom-field-builder-list">
        <?php foreach ($oldIdentityCustom as $index => $field): ?>
          <div class="custom-field-builder-row">
            <div class="field"><label>Nama kolom</label><input name="identity_custom[<?= (int) $index ?>][label]" maxlength="100" value="<?= html_escape(isset($field['label']) ? $field['label'] : '') ?>" placeholder="Contoh: Nama instansi"></div>
            <div class="field"><label>Status</label><select name="identity_custom[<?= (int) $index ?>][mode]"><option value="required" <?= (isset($field['mode']) ? $field['mode'] : 'required') === 'required' ? 'selected' : '' ?>>Wajib</option><option value="optional" <?= (isset($field['mode']) ? $field['mode'] : '') === 'optional' ? 'selected' : '' ?>>Opsional</option></select></div>
            <div class="field"><label>Jenis input</label><select name="identity_custom[<?= (int) $index ?>][type]">
              <?php foreach (['text' => 'Teks', 'email' => 'Email', 'tel' => 'Nomor telepon', 'number' => 'Angka', 'textarea' => 'Teks panjang', 'select' => 'Daftar pilihan'] as $type => $label): ?>
                <option value="<?= $type ?>" <?= (isset($field['type']) ? $field['type'] : 'text') === $type ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select></div>
            <div class="field"><label>Petunjuk</label><input name="identity_custom[<?= (int) $index ?>][help_text]" maxlength="255" value="<?= html_escape(isset($field['help_text']) ? $field['help_text'] : '') ?>" placeholder="Jelaskan cara mengisinya"></div>
            <div class="custom-options-field" hidden>
              <div class="choice-value-heading"><strong>Pilihan dropdown</strong><small>Pilih value angka atau gunakan teks label sebagai value.</small></div>
              <label class="choice-value-mode">Value pilihan<select name="identity_custom[<?= (int) $index ?>][value_mode]" data-choice-value-mode><option value="numeric" <?= isset($field['value_mode']) && $field['value_mode'] === 'label' ? '' : 'selected' ?>>Angka</option><option value="label" <?= isset($field['value_mode']) && $field['value_mode'] === 'label' ? 'selected' : '' ?>>Sama dengan label</option></select></label>
              <div class="choice-value-rows">
                <?php foreach ($customOptions($field) as $optionIndex => $option): ?>
                  <div class="choice-value-row custom-option-row">
                    <label>Label<input name="identity_custom[<?= (int) $index ?>][options][<?= (int) $optionIndex ?>][label]" maxlength="100" value="<?= html_escape($option['label']) ?>" placeholder="Contoh: SD"></label>
                    <label class="choice-value-input-label">Value<input name="identity_custom[<?= (int) $index ?>][options][<?= (int) $optionIndex ?>][value]" type="text" value="<?= html_escape($option['value']) ?>" placeholder="Contoh: 1"></label>
                    <button class="custom-remove-button" type="button" data-remove-choice>Hapus</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="btn btn-secondary btn-sm" type="button" data-add-custom-choice>Tambah pilihan</button>
            </div>
            <button class="custom-remove-button" type="button" data-remove-row aria-label="Hapus identitas">Hapus</button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="builder-actions">
      <button class="btn btn-secondary" type="button" data-builder-back>← Kembali</button>
      <button class="btn btn-primary" type="button" data-builder-next><?= $identityBtnLabel ?></button>
    </div>
  </section>

  <section class="panel builder-step" data-builder-step="2">
    <div class="builder-step-head">
      <span class="builder-step-number">3</span>
      <div>
        <h2>Pilih atau buat pertanyaan survei</h2>
        <p>Lengkapi nama form, lalu pilih pertanyaan yang tersedia atau buat pertanyaan baru langsung di halaman ini.</p>
      </div>
    </div>

    <div class="builder-form-info">
      <div class="field"><label>Nama form <span class="required-mark">*</span></label><input name="form_name" maxlength="150" required value="<?= html_escape($oldValue('form_name')) ?>" placeholder="Contoh: Survei Pelayanan Perizinan"></div>
      <div class="field"><label>Kode form <span class="required-mark">*</span></label><input name="form_code" maxlength="30" required value="<?= html_escape($oldValue('form_code')) ?>" placeholder="SURVEI-PELAYANAN"><small class="field-help">Huruf, angka, garis bawah, atau tanda hubung tanpa spasi.</small></div>
      <div class="field"><label>Nama survei <span class="required-mark">*</span></label><input name="survey_name" maxlength="150" required value="<?= html_escape($oldValue('survey_name')) ?>" placeholder="Survei Pelayanan Perizinan"></div>
      <div class="field"><label>Kode survei <span class="required-mark">*</span></label><input name="survey_code" maxlength="30" required value="<?= html_escape($oldValue('survey_code')) ?>" placeholder="PELAYANAN"><small class="field-help">Kode harus berbeda dari survei yang sudah ada.</small></div>
      <div class="field"><label>Label nilai <span class="required-mark">*</span></label><input name="index_label" maxlength="60" required value="<?= html_escape($oldValue('index_label', 'Nilai Survei')) ?>" placeholder="Nilai Survei"></div>
      <div class="field"><label>Warna grafik</label><input name="color" type="color" value="<?= html_escape($oldValue('color', '#8b5cf6')) ?>"></div>
      <label class="form-toggle-card builder-visibility-card">
        <input type="checkbox" name="is_public_listed" value="1" <?= $oldPublicListed ? 'checked' : '' ?>>
        <span>
          <b>Tampilkan di front office</b>
          <small>Jika dimatikan, formulir hanya dapat dibuka melalui URL shortcut resmi.</small>
        </span>
      </label>
      <div class="field full"><label>Deskripsi</label><textarea name="description" maxlength="2000" placeholder="Jelaskan tujuan survei dengan singkat"><?= html_escape($oldValue('description')) ?></textarea></div>
    </div>

    <div class="builder-subsection">
        <div class="builder-subsection-head">
          <div>
            <h3>Pilih pertanyaan yang sudah ada</h3>
            <p>Pertanyaan dapat digunakan kembali pada beberapa survei. Pilih sesuai kebutihan survei baru.</p>
          </div>
          <span class="badge"><?= count($available_questions) ?> tersedia</span>
        </div>
        <?php if ($available_questions): ?>
          <div class="builder-question-library">
            <input type="search" class="choice-search" placeholder="Cari pertanyaan…" data-filter=".builder-question-option">
          <?php foreach ($available_questions as $questionId => $question): ?>
            <label class="builder-question-option">
              <input type="checkbox" name="question_ids[]" value="<?= (int) $questionId ?>" <?= in_array((int) $questionId, $oldQuestionIds, true) ? 'checked' : '' ?>>
              <span><b><?= html_escape($question['question_code']) ?> · <?= html_escape($question['measurement_name']) ?></b><small><?= html_escape($question['question_text']) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="builder-empty-state">Belum ada pertanyaan tersimpan. Gunakan pembuat pertanyaan baru di bawah.</div>
      <?php endif; ?>
    </div>

    <div class="builder-subsection">
      <div class="builder-subsection-head">
        <div>
          <h3>Buat pertanyaan baru di tempat</h3>
          <p>Setiap pertanyaan memiliki pengukuran, kategori, bobot, dan pilihan jawaban sendiri.</p>
        </div>
        <button class="btn btn-secondary btn-sm" type="button" id="add-inline-question">＋ Tambah pertanyaan</button>
      </div>
      <div id="inline-question-list" class="inline-question-list">
        <?php foreach ($oldQuestions as $questionIndex => $question): ?>
          <?php
          $defaultOptions = [
              ['label' => 'Sangat Tidak Baik', 'value' => 1, 'score' => 25],
              ['label' => 'Tidak Baik', 'value' => 2, 'score' => 50],
              ['label' => 'Baik', 'value' => 3, 'score' => 75],
              ['label' => 'Sangat Baik', 'value' => 4, 'score' => 100],
          ];
          $questionOptions = isset($question['options']) && is_array($question['options']) ? $question['options'] : $defaultOptions;
          ?>
          <article class="inline-question-card">
            <div class="inline-question-head"><strong>Pertanyaan baru <span data-question-number><?= (int) $questionIndex + 1 ?></span></strong><button type="button" data-remove-question>Hapus pertanyaan</button></div>
            <div class="builder-form-info">
              <div class="field full"><label>Teks pertanyaan</label><textarea name="new_questions[<?= (int) $questionIndex ?>][question_text]" maxlength="2000" placeholder="Tuliskan satu pertanyaan yang jelas"><?= html_escape(isset($question['question_text']) ? $question['question_text'] : '') ?></textarea></div>
              <div class="field"><label>Nama pengukuran</label><input name="new_questions[<?= (int) $questionIndex ?>][measurement_name]" maxlength="100" value="<?= html_escape(isset($question['measurement_name']) ? $question['measurement_name'] : '') ?>" placeholder="Contoh: Kecepatan Pelayanan"></div>
              <div class="field"><label>Kategori</label><input name="new_questions[<?= (int) $questionIndex ?>][category_name]" maxlength="100" value="<?= html_escape(isset($question['category_name']) ? $question['category_name'] : '') ?>" placeholder="Contoh: Mutu Pelayanan"></div>
              <div class="field"><label>Bobot</label><input name="new_questions[<?= (int) $questionIndex ?>][weight]" type="number" min="0.01" step="0.01" value="<?= html_escape(isset($question['weight']) ? $question['weight'] : '1.00') ?>"></div>
            </div>
            <div class="inline-option-grid">
              <?php foreach ($questionOptions as $optionIndex => $option): ?>
                <div class="inline-option-row">
                  <span><?= (int) $optionIndex + 1 ?></span>
                  <input aria-label="Label jawaban" name="new_questions[<?= (int) $questionIndex ?>][options][<?= (int) $optionIndex ?>][label]" maxlength="255" value="<?= html_escape(isset($option['label']) ? $option['label'] : '') ?>" placeholder="Teks jawaban">
                  <input aria-label="Nilai jawaban" name="new_questions[<?= (int) $questionIndex ?>][options][<?= (int) $optionIndex ?>][value]" type="number" step="0.01" value="<?= html_escape(isset($option['value']) ? $option['value'] : $optionIndex + 1) ?>" placeholder="Nilai">
                  <input aria-label="Skor normalisasi" name="new_questions[<?= (int) $questionIndex ?>][options][<?= (int) $optionIndex ?>][score]" type="number" min="0" max="100" step="0.01" value="<?= html_escape(isset($option['score']) ? $option['score'] : 0) ?>" placeholder="Skor 0–100">
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="builder-final-note">
      <strong>Setelah disimpan</strong>
      <span><?= $isEditMode
          ? 'Survei, susunan pertanyaan, data responden, dan shortcut publik diperbarui.'
          : 'Survei, susunan pertanyaan, form publik, shortcut, dan pengaturan data responden dibuat sekaligus.' ?></span>
    </div>
    <div class="builder-actions">
      <button class="btn btn-secondary" type="button" data-builder-back>← Kembali</button>
      <button class="btn btn-primary" type="submit"><?= $submitLabel ?></button>
    </div>
  </section>
</form>

<template id="custom-field-template">
  <div class="custom-field-builder-row">
    <div class="field"><label>Nama kolom</label><input data-name="label" maxlength="100" placeholder="Tuliskan nama kolom"></div>
    <div class="field custom-mode-holder"><label>Status</label><select data-name="mode"><option value="required">Wajib</option><option value="optional">Opsional</option></select></div>
    <div class="field"><label>Jenis input</label><select data-name="type"><option value="text">Teks</option><option value="email">Email</option><option value="tel">Nomor telepon</option><option value="number">Angka</option><option value="textarea">Teks panjang</option><option value="select">Daftar pilihan</option></select></div>
    <div class="field"><label>Petunjuk</label><input data-name="help_text" maxlength="255" placeholder="Jelaskan cara mengisinya"></div>
    <div class="custom-options-field" hidden>
      <div class="choice-value-heading"><strong>Pilihan dropdown</strong><small>Pilih value angka atau gunakan teks label sebagai value.</small></div>
      <label class="choice-value-mode">Value pilihan<select data-name="value_mode" data-choice-value-mode><option value="numeric" selected>Angka</option><option value="label">Sama dengan label</option></select></label>
      <div class="choice-value-rows">
        <?php foreach ([1, 2] as $optionIndex): ?>
          <div class="choice-value-row custom-option-row">
            <label>Label<input data-option-index="<?= $optionIndex - 1 ?>" data-option-name="label" maxlength="100" placeholder="Contoh: SD"></label>
            <label class="choice-value-input-label">Value<input data-option-index="<?= $optionIndex - 1 ?>" data-option-name="value" type="text" value="<?= $optionIndex ?>" placeholder="Contoh: <?= $optionIndex ?>"></label>
            <button class="custom-remove-button" type="button" data-remove-choice>Hapus</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-secondary btn-sm" type="button" data-add-custom-choice>Tambah pilihan</button>
    </div>
    <button class="custom-remove-button" type="button" data-remove-row>Hapus</button>
  </div>
</template>

<template id="inline-question-template">
  <article class="inline-question-card">
    <div class="inline-question-head"><strong>Pertanyaan baru <span data-question-number></span></strong><button type="button" data-remove-question>Hapus pertanyaan</button></div>
    <div class="builder-form-info">
      <div class="field full"><label>Teks pertanyaan</label><textarea data-question-name="question_text" maxlength="2000" placeholder="Tuliskan satu pertanyaan yang jelas"></textarea></div>
      <div class="field"><label>Nama pengukuran</label><input data-question-name="measurement_name" maxlength="100" placeholder="Contoh: Kecepatan Pelayanan"></div>
      <div class="field"><label>Kategori</label><input data-question-name="category_name" maxlength="100" placeholder="Contoh: Mutu Pelayanan"></div>
      <div class="field"><label>Bobot</label><input data-question-name="weight" type="number" min="0.01" step="0.01" value="1.00"></div>
    </div>
    <div class="inline-option-grid">
      <?php foreach ([['Sangat Tidak Baik', 1, 25], ['Tidak Baik', 2, 50], ['Baik', 3, 75], ['Sangat Baik', 4, 100]] as $optionIndex => $option): ?>
        <div class="inline-option-row">
          <span><?= $optionIndex + 1 ?></span>
          <input data-option-index="<?= $optionIndex ?>" data-option-name="label" maxlength="255" value="<?= html_escape($option[0]) ?>">
          <input data-option-index="<?= $optionIndex ?>" data-option-name="value" type="number" step="0.01" value="<?= $option[1] ?>">
          <input data-option-index="<?= $optionIndex ?>" data-option-name="score" type="number" min="0" max="100" step="0.01" value="<?= $option[2] ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</template>

<script src="<?= base_url('assets/ipak/js/form-builder.js') ?>"></script>
