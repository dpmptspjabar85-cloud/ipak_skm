<?php
$isEdit = !empty($client['id']);
$formAction = $isEdit
    ? site_url('admin/api-builder/edit/' . (int) $client['id'])
    : site_url('admin/api-builder/create');
$endpointBase = site_url('api/v1/survey-data/');
$expiresValue = !empty($client['expires_at'])
    ? date('Y-m-d\TH:i', strtotime($client['expires_at']))
    : '';
?>

<div class="api-form-toolbar">
  <div>
    <span class="step-kicker"><?= $isEdit ? 'Perbarui integrasi' : 'Integrasi baru' ?></span>
    <p>Ikuti empat bagian berikut. Tanda data pribadi membantu Anda membatasi informasi sensitif hanya untuk peminta yang berwenang.</p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/api-builder') ?>">Kembali ke API Builder</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <strong>Konfigurasi belum dapat disimpan.</strong>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= html_escape($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="api-builder-form" method="post" action="<?= $formAction ?>">
  <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

  <section class="panel api-form-section">
    <div class="api-form-section-head">
      <b>1</b>
      <div>
        <h2>Identitas aplikasi peminta</h2>
        <p>Informasi ini membedakan setiap aplikasi dan membentuk alamat endpoint.</p>
      </div>
    </div>
    <div class="api-form-grid">
      <div class="field">
        <label for="client_name">Nama aplikasi atau instansi peminta <span class="required-mark">*</span></label>
        <input id="client_name" name="client_name" type="text" required maxlength="120" value="<?= html_escape($client['client_name']) ?>" placeholder="Contoh: Dashboard Diskominfo">
        <small class="field-help"><b>Informasi:</b> Gunakan nama yang mudah dikenali oleh pengelola backoffice.</small>
      </div>
      <div class="field">
        <label for="client_code">Kode endpoint <span class="required-mark">*</span></label>
        <input id="client_code" name="client_code" type="text" required maxlength="50" pattern="[a-z0-9][a-z0-9_-]{2,49}" value="<?= html_escape($client['client_code']) ?>" placeholder="contoh: dashboard_diskominfo">
        <small class="field-help"><b>Informasi:</b> Hanya huruf kecil, angka, tanda hubung, atau garis bawah. Kode menjadi bagian dari URL API.</small>
      </div>
      <div class="field api-form-grid-wide">
        <label for="description">Catatan kebutuhan data</label>
        <textarea id="description" name="description" maxlength="255" rows="3" placeholder="Contoh: Menampilkan nilai SKM tahunan tanpa data pribadi"><?= html_escape($client['description']) ?></textarea>
        <small class="field-help"><b>Informasi:</b> Catat tujuan penggunaan agar akses mudah ditinjau kembali.</small>
      </div>
      <div class="api-endpoint-live api-form-grid-wide">
        <span>Perkiraan endpoint</span>
        <code id="api-endpoint-preview"><?= html_escape($endpointBase . ($client['client_code'] ?: 'kode_endpoint')) ?></code>
      </div>
    </div>
  </section>

  <section class="panel api-form-section">
    <div class="api-form-section-head">
      <b>2</b>
      <div>
        <h2>Pilih survei dan jenis keluaran</h2>
        <p>Aplikasi peminta tidak dapat mengakses survei atau keluaran di luar pilihan ini.</p>
      </div>
    </div>

    <fieldset class="api-fieldset">
      <legend>Cakupan survei <span class="required-mark">*</span></legend>
      <p class="api-fieldset-help"><b>Informasi:</b> Pilih survei tertentu untuk akses paling aman. “Semua” juga mencakup survei baru yang dibuat kemudian.</p>
      <div class="api-choice-row">
        <label class="api-radio-card">
          <input type="radio" name="survey_scope" value="selected" <?= $client['survey_scope'] === 'selected' ? 'checked' : '' ?>>
          <span><b>Survei tertentu</b><small>Pilih satu atau beberapa survei secara manual.</small></span>
        </label>
        <label class="api-radio-card">
          <input type="radio" name="survey_scope" value="all" <?= $client['survey_scope'] === 'all' ? 'checked' : '' ?>>
          <span><b>Semua survei aktif</b><small>Survei baru otomatis ikut dapat dibaca.</small></span>
        </label>
      </div>
    </fieldset>

    <fieldset id="api-survey-selection" class="api-fieldset">
      <legend>Survei yang diizinkan</legend>
      <p class="api-fieldset-help"><b>Informasi:</b> Aplikasi peminta dapat memilih data per survei menggunakan kode, ID, atau UUID survei.</p>
      <div class="api-check-grid">
        <?php foreach ($surveys as $surveyId => $survey): ?>
          <label class="api-check-card">
            <input type="checkbox" name="allowed_survey_ids[]" value="<?= (int) $surveyId ?>" <?= in_array((int) $surveyId, array_map('intval', $client['allowed_survey_ids']), true) ? 'checked' : '' ?>>
            <span>
              <b><?= html_escape($survey['survey_name']) ?></b>
              <small><?= html_escape($survey['survey_code']) ?> · <?= html_escape($survey['index_label']) ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="api-fieldset">
      <legend>Jenis keluaran API <span class="required-mark">*</span></legend>
      <p class="api-fieldset-help"><b>Informasi:</b> Pilih hanya keluaran yang benar-benar diperlukan oleh aplikasi peminta.</p>
      <div class="api-check-grid">
        <?php foreach ($resources as $resourceKey => $resource): ?>
          <label class="api-check-card">
            <input type="checkbox" name="allowed_resources[]" value="<?= html_escape($resourceKey) ?>" <?= in_array($resourceKey, $client['allowed_resources'], true) ? 'checked' : '' ?>>
            <span>
              <b><?= html_escape($resource['label']) ?></b>
              <small><?= html_escape($resource['help']) ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </section>

  <section class="panel api-form-section">
    <div class="api-form-section-head">
      <b>3</b>
      <div>
        <h2>Atur isi data</h2>
        <p>Dimensi grafik dan kolom detail dapat berbeda untuk setiap peminta API.</p>
      </div>
    </div>

    <fieldset id="api-chart-settings" class="api-fieldset">
      <legend>Dimensi grafik yang diizinkan</legend>
      <p class="api-fieldset-help"><b>Informasi:</b> Peminta memilih salah satu dimensi melalui parameter <code>dimension</code>.</p>
      <div class="api-compact-check-grid">
        <?php foreach ($dimensions as $dimensionKey => $dimensionLabel): ?>
          <label>
            <input type="checkbox" name="allowed_dimensions[]" value="<?= html_escape($dimensionKey) ?>" <?= in_array($dimensionKey, $client['allowed_dimensions'], true) ? 'checked' : '' ?>>
            <span><?= html_escape($dimensionLabel) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset id="api-detail-settings" class="api-fieldset">
      <legend>Kolom detail respons yang dikirim</legend>
      <p class="api-fieldset-help"><b>Informasi:</b> Kolom bertanda “Data pribadi” sebaiknya dipilih hanya jika ada dasar dan kebutuhan resmi.</p>
      <div class="api-compact-check-grid api-detail-field-grid">
        <?php foreach ($detail_fields as $fieldKey => $field): ?>
          <label class="<?= $field['privacy'] ? 'api-private-field' : '' ?>">
            <input type="checkbox" name="allowed_detail_fields[]" value="<?= html_escape($fieldKey) ?>" <?= in_array($fieldKey, $client['allowed_detail_fields'], true) ? 'checked' : '' ?>>
            <span>
              <?= html_escape($field['label']) ?>
              <?php if ($field['privacy']): ?><small>Data pribadi</small><?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </section>

  <section class="panel api-form-section">
    <div class="api-form-section-head">
      <b>4</b>
      <div>
        <h2>Keamanan dan batas akses</h2>
        <p>Batasi jumlah data, frekuensi permintaan, jaringan, dan masa berlaku akses.</p>
      </div>
    </div>
    <div class="api-form-grid">
      <div class="field">
        <label for="max_page_size">Maksimal data per halaman <span class="required-mark">*</span></label>
        <input id="max_page_size" name="max_page_size" type="number" required min="1" max="500" value="<?= (int) $client['max_page_size'] ?>">
        <small class="field-help"><b>Informasi:</b> Membatasi banyaknya detail respons dalam satu permintaan. Rentang 1–500.</small>
      </div>
      <div class="field">
        <label for="rate_limit_per_minute">Maksimal permintaan per menit <span class="required-mark">*</span></label>
        <input id="rate_limit_per_minute" name="rate_limit_per_minute" type="number" required min="1" max="600" value="<?= (int) $client['rate_limit_per_minute'] ?>">
        <small class="field-help"><b>Informasi:</b> Permintaan berikutnya ditolak sementara jika batas terlampaui. Rentang 1–600.</small>
      </div>
      <div class="field">
        <label for="allowed_origin">Asal aplikasi web yang diizinkan</label>
        <input id="allowed_origin" name="allowed_origin" type="text" maxlength="255" value="<?= html_escape($client['allowed_origin']) ?>" placeholder="https://aplikasi.jabarprov.go.id">
        <small class="field-help"><b>Informasi:</b> Isi origin tanpa path untuk CORS. Kosongkan jika API dipanggil dari server, atau gunakan <code>*</code> hanya untuk data publik.</small>
      </div>
      <div class="field">
        <label for="expires_at">Akses berakhir pada</label>
        <input id="expires_at" name="expires_at" type="datetime-local" value="<?= html_escape($expiresValue) ?>">
        <small class="field-help"><b>Informasi:</b> Kosongkan jika tidak memiliki batas tanggal. Akses otomatis ditolak setelah waktu ini.</small>
      </div>
      <div class="field api-form-grid-wide">
        <label for="allowed_ip_addresses">Alamat IP server yang diizinkan</label>
        <textarea id="allowed_ip_addresses" name="allowed_ip_addresses" rows="3" maxlength="2000" placeholder="Contoh: 103.10.20.30&#10;2001:db8::1"><?= html_escape($client['allowed_ip_addresses']) ?></textarea>
        <small class="field-help"><b>Informasi:</b> Satu IP per baris atau dipisahkan koma. Kosongkan apabila alamat server peminta belum tetap.</small>
      </div>
      <div class="field api-form-grid-wide">
        <input type="hidden" name="is_active" value="0">
        <label class="api-switch-row" for="is_active">
          <input id="is_active" name="is_active" type="checkbox" value="1" <?= !empty($client['is_active']) ? 'checked' : '' ?>>
          <span><b>Aktifkan akses setelah disimpan</b><small>Jika dimatikan, endpoint dan kunci tetap tersimpan tetapi seluruh permintaan ditolak.</small></span>
        </label>
      </div>
    </div>
  </section>

  <div class="api-form-actions">
    <a class="btn btn-secondary" href="<?= site_url('admin/api-builder') ?>">Batal</a>
    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Simpan perubahan API' : 'Buat endpoint dan kunci API' ?></button>
  </div>
</form>

<script>
(function () {
  var codeInput = document.getElementById('client_code');
  var preview = document.getElementById('api-endpoint-preview');
  var endpointBase = <?= json_encode($endpointBase) ?>;
  var surveySection = document.getElementById('api-survey-selection');
  var chartSection = document.getElementById('api-chart-settings');
  var detailSection = document.getElementById('api-detail-settings');

  function normalizeCode(value) {
    return value.toLowerCase().replace(/[^a-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 50);
  }
  function updateEndpoint() {
    codeInput.value = normalizeCode(codeInput.value);
    preview.textContent = endpointBase + (codeInput.value || 'kode_endpoint');
  }
  function resourceChecked(value) {
    var field = document.querySelector('input[name="allowed_resources[]"][value="' + value + '"]');
    return field && field.checked;
  }
  function updateConditionalSections() {
    var scope = document.querySelector('input[name="survey_scope"]:checked');
    surveySection.hidden = scope && scope.value === 'all';
    chartSection.hidden = !resourceChecked('chart');
    detailSection.hidden = !resourceChecked('details');
  }

  codeInput.addEventListener('input', updateEndpoint);
  Array.prototype.forEach.call(document.querySelectorAll('input[name="survey_scope"], input[name="allowed_resources[]"]'), function (field) {
    field.addEventListener('change', updateConditionalSections);
  });
  updateEndpoint();
  updateConditionalSections();
})();
</script>
