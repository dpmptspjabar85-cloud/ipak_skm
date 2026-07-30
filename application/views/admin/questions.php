<?php if ($question_success): ?>
  <div class="alert question-alert-success"><?= html_escape($question_success) ?></div>
<?php endif; ?>
<?php if ($question_error): ?>
  <div class="alert alert-error"><?= html_escape($question_error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">i</div>
  <div>
    <strong>Kelola pertanyaan berdasarkan survei</strong>
    <p>Pilih survei untuk menampilkan pertanyaan di dalamnya. Tanpa filter, daftar umum dibagi menjadi beberapa halaman agar lebih mudah dibaca.</p>
  </div>
  <?php if ($is_superadmin): ?>
    <a class="btn btn-primary btn-sm" href="<?= site_url('admin/questions/create') ?>">＋ Tambah pertanyaan</a>
  <?php endif; ?>
</section>

<form class="filter-card" method="get" action="<?= site_url('admin/questions') ?>" style="margin-top:18px">
  <div class="filter-grid">
    <div>
      <label for="survey_id">Pilih survei</label>
      <select id="survey_id" name="survey_id">
        <option value="">Semua pertanyaan</option>
        <?php foreach ($surveys as $surveyValue => $survey): ?>
          <option value="<?= (int) $surveyValue ?>" <?= (int) $survey_id === (int) $surveyValue ? 'selected' : '' ?>>
            <?= html_escape($survey['survey_name']) ?> (<?= html_escape($survey['survey_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Daftar hanya akan menampilkan pertanyaan yang menjadi bagian survei terpilih.</small>
    </div>
    <div class="filter-actions">
      <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/questions') ?>">Reset</a>
      <button class="btn btn-primary btn-sm" type="submit">Terapkan filter</button>
    </div>
  </div>
</form>

<section class="panel" style="margin-top:18px">
  <div class="panel-head">
    <div>
      <h2><?= $selected_survey ? 'Pertanyaan ' . html_escape($selected_survey['survey_name']) : 'Semua pertanyaan' ?></h2>
      <p>
        <?= $selected_survey
            ? 'Menampilkan seluruh pertanyaan yang tersusun dalam survei ini.'
            : 'Menampilkan maksimal ' . (int) $per_page . ' pertanyaan pada setiap halaman.' ?>
      </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="badge"><?= number_format((int) $total_questions) ?> pertanyaan</span>
      <span class="badge"><?= $is_superadmin ? 'Mode superadmin' : 'Hanya lihat' ?></span>
    </div>
  </div>

  <?php if (!$is_superadmin): ?>
    <div class="alert question-readonly-note">Perubahan pertanyaan dan pilihan jawaban hanya dapat dilakukan oleh superadmin.</div>
  <?php endif; ?>

  <?php if (!$question_definitions): ?>
    <div class="builder-empty-state">
      <?= $selected_survey
          ? 'Survei ini belum memiliki pertanyaan.'
          : 'Belum ada pertanyaan yang dapat ditampilkan.' ?>
    </div>
  <?php endif; ?>

  <div class="question-admin-list">
    <?php foreach ($question_definitions as $questionId => $question): ?>
      <?php $assignment = isset($question_assignments[$questionId]) ? $question_assignments[$questionId] : []; ?>
      <details class="question-editor">
        <summary>
          <span class="question-editor-code"><?= html_escape($question['question_code']) ?></span>
          <span class="question-editor-copy">
            <strong><?= html_escape($question['measurement_name']) ?></strong>
            <small><?= html_escape($question['question_text']) ?></small>
          </span>
          <span class="question-editor-meta">
            <?= $assignment ? html_escape($assignment['survey_name']) : 'Belum digunakan' ?> · Bobot <?= number_format((float) $question['weight'], 2) ?>
          </span>
          <span class="badge"><?= (int) $question['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
        </summary>

        <div class="question-editor-body">
          <?php if ($is_superadmin): ?>
            <form method="post" action="<?= site_url('admin/questions') ?>">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="question_id" value="<?= (int) $questionId ?>">
              <input type="hidden" name="survey_filter" value="<?= (int) $survey_id ?>">
              <input type="hidden" name="page" value="<?= (int) $page ?>">

              <div class="question-config-grid">
                <div class="field">
                  <label>Kode pertanyaan</label>
                  <input name="question_code" maxlength="20" required value="<?= html_escape($question['question_code']) ?>">
                  <small class="field-help"><b>Informasi:</b> Kode unik, misalnya SKM-01 atau IPAK-01.</small>
                </div>
                <div class="field">
                  <label>Urutan</label>
                  <input name="sort_order" type="number" required value="<?= (int) $question['sort_order'] ?>">
                  <small class="field-help"><b>Informasi:</b> Angka kecil ditampilkan lebih dahulu.</small>
                </div>
                <div class="field">
                  <label>Nama pengukuran</label>
                  <input name="measurement_name" maxlength="100" required value="<?= html_escape($question['measurement_name']) ?>">
                  <small class="field-help"><b>Informasi:</b> Aspek utama yang diukur.</small>
                </div>
                <div class="field">
                  <label>Kategori</label>
                  <input name="category_name" maxlength="100" required value="<?= html_escape($question['category_name']) ?>">
                  <small class="field-help"><b>Informasi:</b> Kelompok analisis pertanyaan.</small>
                </div>
                <div class="field">
                  <label>Bobot</label>
                  <input name="weight" type="number" min="0.01" step="0.01" required value="<?= html_escape(number_format((float) $question['weight'], 2, '.', '')) ?>">
                  <small class="field-help"><b>Informasi:</b> Menentukan pengaruh terhadap nilai survei.</small>
                </div>
                <label class="question-active-check"><input type="checkbox" name="is_active" value="1" <?= (int) $question['is_active'] === 1 ? 'checked' : '' ?>> Pertanyaan aktif</label>
                <div class="field full">
                  <label>Teks pertanyaan</label>
                  <textarea name="question_text" maxlength="2000" required><?= html_escape($question['question_text']) ?></textarea>
                  <small class="field-help"><b>Informasi:</b> Gunakan kalimat singkat dan hanya menanyakan satu hal.</small>
                </div>
              </div>

              <div class="option-editor-head">
                <div><h3>Pilihan jawaban</h3><p>Normalisasi adalah skor 0–100 yang dipakai pada perhitungan.</p></div>
              </div>
              <div class="table-wrap">
                <table class="data-table option-editor-table">
                  <thead><tr><th>Kode</th><th>Label jawaban</th><th>Nilai</th><th>Normalisasi</th><th>Urutan</th><th>Aktif</th></tr></thead>
                  <tbody>
                    <?php foreach ($question['options'] as $optionIndex => $option): ?>
                      <tr>
                        <td>
                          <input type="hidden" name="options[<?= (int) $optionIndex ?>][id]" value="<?= (int) $option['id'] ?>">
                          <input name="options[<?= (int) $optionIndex ?>][option_code]" maxlength="20" required value="<?= html_escape($option['option_code']) ?>" aria-label="Kode pilihan <?= (int) $optionIndex + 1 ?>">
                        </td>
                        <td><input name="options[<?= (int) $optionIndex ?>][option_label]" maxlength="255" required value="<?= html_escape($option['option_label']) ?>" aria-label="Label pilihan <?= (int) $optionIndex + 1 ?>"></td>
                        <td><input name="options[<?= (int) $optionIndex ?>][option_value]" type="number" step="0.01" required value="<?= html_escape(number_format((float) $option['option_value'], 2, '.', '')) ?>" aria-label="Nilai pilihan <?= (int) $optionIndex + 1 ?>"></td>
                        <td><input name="options[<?= (int) $optionIndex ?>][normalized_score]" type="number" min="0" max="100" step="0.01" required value="<?= html_escape(number_format((float) $option['normalized_score'], 2, '.', '')) ?>" aria-label="Normalisasi pilihan <?= (int) $optionIndex + 1 ?>"></td>
                        <td><input name="options[<?= (int) $optionIndex ?>][sort_order]" type="number" required value="<?= (int) $option['sort_order'] ?>" aria-label="Urutan pilihan <?= (int) $optionIndex + 1 ?>"></td>
                        <td><input name="options[<?= (int) $optionIndex ?>][is_active]" type="checkbox" value="1" <?= (int) $option['is_active'] === 1 ? 'checked' : '' ?> aria-label="Aktifkan pilihan <?= (int) $optionIndex + 1 ?>"></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php $newOptionIndex = count($question['options']); ?>
                    <tr class="option-new-row">
                      <td><input name="options[<?= $newOptionIndex ?>][option_code]" maxlength="20" placeholder="Kode baru" aria-label="Kode pilihan baru"></td>
                      <td><input name="options[<?= $newOptionIndex ?>][option_label]" maxlength="255" placeholder="Pilihan baru" aria-label="Label pilihan baru"></td>
                      <td><input name="options[<?= $newOptionIndex ?>][option_value]" type="number" step="0.01" placeholder="Nilai" aria-label="Nilai pilihan baru"></td>
                      <td><input name="options[<?= $newOptionIndex ?>][normalized_score]" type="number" min="0" max="100" step="0.01" placeholder="0–100" aria-label="Normalisasi pilihan baru"></td>
                      <td><input name="options[<?= $newOptionIndex ?>][sort_order]" type="number" value="<?= $newOptionIndex + 1 ?>" aria-label="Urutan pilihan baru"></td>
                      <td><input name="options[<?= $newOptionIndex ?>][is_active]" type="checkbox" value="1" checked aria-label="Aktifkan pilihan baru"></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="question-editor-actions"><button class="btn btn-primary btn-sm" type="submit">Simpan perubahan</button></div>
            </form>

            <form method="post" action="<?= site_url('admin/questions') ?>" class="question-toggle-form">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="question_id" value="<?= (int) $questionId ?>">
              <input type="hidden" name="survey_filter" value="<?= (int) $survey_id ?>">
              <input type="hidden" name="page" value="<?= (int) $page ?>">
              <input type="hidden" name="is_active" value="<?= (int) $question['is_active'] === 1 ? 0 : 1 ?>">
              <button class="btn btn-secondary btn-sm" type="submit"><?= (int) $question['is_active'] === 1 ? 'Nonaktifkan pertanyaan' : 'Aktifkan pertanyaan' ?></button>
            </form>
          <?php else: ?>
            <dl class="question-readonly-details">
              <dt>Pertanyaan</dt><dd><?= html_escape($question['question_text']) ?></dd>
              <dt>Pengukuran</dt><dd><?= html_escape($question['measurement_name']) ?></dd>
              <dt>Kategori</dt><dd><?= html_escape($question['category_name']) ?></dd>
              <dt>Pilihan</dt>
              <dd><?php foreach ($question['options'] as $option): ?><span class="badge"><?= html_escape($option['option_label']) ?> = <?= number_format((float) $option['normalized_score'], 0) ?></span><?php endforeach; ?></dd>
            </dl>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>

  <?php if (!$selected_survey && $total_pages > 1): ?>
    <div class="pagination">
      <span>Menampilkan <?= count($question_definitions) ?> dari <?= number_format((int) $total_questions) ?> pertanyaan</span>
      <div class="pagination-links">
        <?php if ($page > 1): ?><a href="<?= site_url('admin/questions') . '?page=' . ((int) $page - 1) ?>">← Sebelumnya</a><?php endif; ?>
        <span style="padding:8px 4px">Halaman <?= (int) $page ?> / <?= (int) $total_pages ?></span>
        <?php if ($page < $total_pages): ?><a href="<?= site_url('admin/questions') . '?page=' . ((int) $page + 1) ?>">Berikutnya →</a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
