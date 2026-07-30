<?php
$questionCode = isset($old['question_code']) ? $old['question_code'] : '';
$questionText = isset($old['question_text']) ? $old['question_text'] : '';
$measurementName = isset($old['measurement_name']) ? $old['measurement_name'] : '';
$categoryName = isset($old['category_name']) ? $old['category_name'] : '';
$weight = isset($old['weight']) ? $old['weight'] : '1.00';
$sortOrder = isset($old['sort_order']) ? $old['sort_order'] : $next_sort_order;
$isActive = !$old || !empty($old['is_active']);
$optionRows = isset($old['options']) && is_array($old['options']) ? $old['options'] : [
    ['option_code' => 'STS', 'option_label' => 'Sangat Tidak Setuju', 'option_value' => 1, 'normalized_score' => 25, 'sort_order' => 1, 'is_active' => 1],
    ['option_code' => 'TS', 'option_label' => 'Tidak Setuju', 'option_value' => 2, 'normalized_score' => 50, 'sort_order' => 2, 'is_active' => 1],
    ['option_code' => 'S', 'option_label' => 'Setuju', 'option_value' => 3, 'normalized_score' => 75, 'sort_order' => 3, 'is_active' => 1],
    ['option_code' => 'SS', 'option_label' => 'Sangat Setuju', 'option_value' => 4, 'normalized_score' => 100, 'sort_order' => 4, 'is_active' => 1],
];
?>

<?php if ($question_error): ?>
  <div class="alert alert-error"><?= html_escape($question_error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">1</div>
  <div>
    <strong>Buat pertanyaan pada halaman khusus</strong>
    <p>Isi identitas pertanyaan terlebih dahulu. Gunakan tombol “Tambah pilihan jawaban” untuk membuka modal dan menambahkan jawaban tanpa memenuhi halaman.</p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/questions') ?>">← Kembali ke daftar</a>
</section>

<form method="post" action="<?= site_url('admin/questions/create') ?>" class="panel" style="margin-top:18px">
  <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

  <div class="panel-head">
    <div>
      <h2>Informasi pertanyaan</h2>
      <p>Gunakan bahasa sederhana dan satu pertanyaan hanya untuk satu aspek pengukuran.</p>
    </div>
    <span class="badge">Superadmin</span>
  </div>

  <div class="question-config-grid">
    <div class="field">
      <label for="question_code">Kode pertanyaan <span class="required-mark">*</span></label>
      <input id="question_code" name="question_code" maxlength="20" required value="<?= html_escape($questionCode) ?>" placeholder="Contoh: IPAK-11">
      <small class="field-help"><b>Petunjuk:</b> Gunakan huruf, angka, garis bawah, atau tanda hubung tanpa spasi.</small>
    </div>
    <div class="field">
      <label for="sort_order">Urutan <span class="required-mark">*</span></label>
      <input id="sort_order" name="sort_order" type="number" required value="<?= (int) $sortOrder ?>">
      <small class="field-help"><b>Petunjuk:</b> Angka kecil akan ditampilkan lebih dahulu.</small>
    </div>
    <div class="field">
      <label for="measurement_name">Nama pengukuran <span class="required-mark">*</span></label>
      <input id="measurement_name" name="measurement_name" maxlength="100" required value="<?= html_escape($measurementName) ?>" placeholder="Contoh: Kecepatan Pelayanan">
      <small class="field-help"><b>Petunjuk:</b> Nama aspek yang akan dianalisis pada dashboard.</small>
    </div>
    <div class="field">
      <label for="category_name">Kategori <span class="required-mark">*</span></label>
      <input id="category_name" name="category_name" maxlength="100" required value="<?= html_escape($categoryName) ?>" placeholder="Contoh: Mutu Pelayanan">
      <small class="field-help"><b>Petunjuk:</b> Kelompok besar dari aspek pengukuran.</small>
    </div>
    <div class="field">
      <label for="weight">Bobot <span class="required-mark">*</span></label>
      <input id="weight" name="weight" type="number" min="0.01" step="0.01" required value="<?= html_escape($weight) ?>">
      <small class="field-help"><b>Petunjuk:</b> Gunakan 1,00 untuk bobot normal.</small>
    </div>
    <label class="question-active-check"><input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>> Pertanyaan langsung aktif</label>
    <div class="field full">
      <label for="question_text">Teks pertanyaan <span class="required-mark">*</span></label>
      <textarea id="question_text" name="question_text" maxlength="2000" required placeholder="Tuliskan pertanyaan yang hanya mengukur satu hal"><?= html_escape($questionText) ?></textarea>
      <small class="field-help"><b>Petunjuk:</b> Hindari istilah teknis, singkatan yang tidak umum, dan pertanyaan ganda.</small>
    </div>
  </div>

  <div class="option-editor-head">
    <div>
      <h3>Pilihan jawaban</h3>
      <p>Minimal dua pilihan aktif. Nilai normalisasi digunakan untuk perhitungan skor 0–100.</p>
    </div>
    <button class="btn btn-secondary btn-sm" id="open-option-modal" type="button">＋ Tambah pilihan jawaban</button>
  </div>

  <div class="table-wrap">
    <table class="data-table option-editor-table">
      <thead>
        <tr><th>Kode</th><th>Label jawaban</th><th>Nilai</th><th>Normalisasi</th><th>Urutan</th><th>Aktif</th><th>Aksi</th></tr>
      </thead>
      <tbody id="new-option-rows" data-next-index="<?= count($optionRows) ?>">
        <?php foreach ($optionRows as $index => $option): ?>
          <tr>
            <td><input name="options[<?= (int) $index ?>][option_code]" maxlength="20" required value="<?= html_escape(isset($option['option_code']) ? $option['option_code'] : '') ?>" aria-label="Kode pilihan <?= (int) $index + 1 ?>"></td>
            <td><input name="options[<?= (int) $index ?>][option_label]" maxlength="255" required value="<?= html_escape(isset($option['option_label']) ? $option['option_label'] : '') ?>" aria-label="Label pilihan <?= (int) $index + 1 ?>"></td>
            <td><input name="options[<?= (int) $index ?>][option_value]" type="number" step="0.01" required value="<?= html_escape(isset($option['option_value']) ? $option['option_value'] : '') ?>" aria-label="Nilai pilihan <?= (int) $index + 1 ?>"></td>
            <td><input name="options[<?= (int) $index ?>][normalized_score]" type="number" min="0" max="100" step="0.01" required value="<?= html_escape(isset($option['normalized_score']) ? $option['normalized_score'] : '') ?>" aria-label="Normalisasi pilihan <?= (int) $index + 1 ?>"></td>
            <td><input name="options[<?= (int) $index ?>][sort_order]" type="number" required value="<?= (int) (isset($option['sort_order']) ? $option['sort_order'] : ($index + 1)) ?>" aria-label="Urutan pilihan <?= (int) $index + 1 ?>"></td>
            <td><input name="options[<?= (int) $index ?>][is_active]" type="checkbox" value="1" <?= !empty($option['is_active']) ? 'checked' : '' ?> aria-label="Aktifkan pilihan <?= (int) $index + 1 ?>"></td>
            <td><button class="btn btn-secondary btn-sm remove-new-option" type="button">Hapus</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="question-editor-actions">
    <a class="btn btn-secondary" href="<?= site_url('admin/questions') ?>">Batal</a>
    <button class="btn btn-primary" type="submit">Simpan pertanyaan dan jawaban</button>
  </div>
</form>

<dialog class="question-option-dialog" id="option-modal" aria-labelledby="option-modal-title">
  <form id="option-modal-form">
    <div class="question-option-dialog-head">
      <div>
        <span class="eyebrow">Pilihan baru</span>
        <h2 id="option-modal-title">Tambah pilihan jawaban</h2>
        <p>Isi seluruh informasi agar pilihan dapat dihitung dengan benar.</p>
      </div>
      <button class="dialog-close" id="close-option-modal" type="button" aria-label="Tutup modal">×</button>
    </div>
    <div class="question-config-grid">
      <div class="field">
        <label for="modal-option-code">Kode <span class="required-mark">*</span></label>
        <input id="modal-option-code" maxlength="20" required placeholder="Contoh: B">
      </div>
      <div class="field">
        <label for="modal-option-label">Label jawaban <span class="required-mark">*</span></label>
        <input id="modal-option-label" maxlength="255" required placeholder="Teks yang dilihat responden">
      </div>
      <div class="field">
        <label for="modal-option-value">Nilai <span class="required-mark">*</span></label>
        <input id="modal-option-value" type="number" step="0.01" required placeholder="1">
      </div>
      <div class="field">
        <label for="modal-option-score">Normalisasi <span class="required-mark">*</span></label>
        <input id="modal-option-score" type="number" min="0" max="100" step="0.01" required placeholder="0–100">
      </div>
      <div class="field">
        <label for="modal-option-order">Urutan <span class="required-mark">*</span></label>
        <input id="modal-option-order" type="number" required value="<?= count($optionRows) + 1 ?>">
      </div>
      <label class="question-active-check"><input id="modal-option-active" type="checkbox" checked> Pilihan aktif</label>
    </div>
    <div class="question-option-dialog-actions">
      <button class="btn btn-secondary" id="cancel-option-modal" type="button">Batal</button>
      <button class="btn btn-primary" id="save-option-modal" type="button">Tambahkan ke daftar</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var modal = document.getElementById('option-modal');
  var modalForm = document.getElementById('option-modal-form');
  var rows = document.getElementById('new-option-rows');
  var openButton = document.getElementById('open-option-modal');
  var closeButtons = [document.getElementById('close-option-modal'), document.getElementById('cancel-option-modal')];

  function openModal() {
    document.getElementById('modal-option-order').value = rows.querySelectorAll('tr').length + 1;
    if (typeof modal.showModal === 'function') {
      modal.showModal();
    } else {
      modal.setAttribute('open', 'open');
    }
    document.getElementById('modal-option-code').focus();
  }

  function closeModal() {
    if (typeof modal.close === 'function') {
      modal.close();
    } else {
      modal.removeAttribute('open');
    }
  }

  function input(name, type, value, attributes) {
    var element = document.createElement('input');
    element.name = name;
    element.type = type;
    element.value = value;
    Object.keys(attributes || {}).forEach(function (key) {
      element.setAttribute(key, attributes[key]);
    });
    return element;
  }

  function cellWith(element) {
    var cell = document.createElement('td');
    cell.appendChild(element);
    return cell;
  }

  openButton.addEventListener('click', openModal);
  closeButtons.forEach(function (button) { button.addEventListener('click', closeModal); });
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });

  document.getElementById('save-option-modal').addEventListener('click', function () {
    if (!modalForm.checkValidity()) {
      modalForm.reportValidity();
      return;
    }
    var index = parseInt(rows.getAttribute('data-next-index'), 10);
    rows.setAttribute('data-next-index', index + 1);
    var prefix = 'options[' + index + ']';
    var row = document.createElement('tr');
    row.appendChild(cellWith(input(prefix + '[option_code]', 'text', document.getElementById('modal-option-code').value.toUpperCase(), {required: 'required', maxlength: '20'})));
    row.appendChild(cellWith(input(prefix + '[option_label]', 'text', document.getElementById('modal-option-label').value, {required: 'required', maxlength: '255'})));
    row.appendChild(cellWith(input(prefix + '[option_value]', 'number', document.getElementById('modal-option-value').value, {required: 'required', step: '0.01'})));
    row.appendChild(cellWith(input(prefix + '[normalized_score]', 'number', document.getElementById('modal-option-score').value, {required: 'required', min: '0', max: '100', step: '0.01'})));
    row.appendChild(cellWith(input(prefix + '[sort_order]', 'number', document.getElementById('modal-option-order').value, {required: 'required'})));
    var active = input(prefix + '[is_active]', 'checkbox', '1', {});
    active.checked = document.getElementById('modal-option-active').checked;
    row.appendChild(cellWith(active));
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-secondary btn-sm remove-new-option';
    remove.textContent = 'Hapus';
    row.appendChild(cellWith(remove));
    rows.appendChild(row);
    modalForm.reset();
    document.getElementById('modal-option-active').checked = true;
    closeModal();
  });

  rows.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-new-option')) {
      event.target.closest('tr').remove();
    }
  });
})();
</script>
