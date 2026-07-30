<?php if ($unit_success): ?>
  <div class="alert question-alert-success"><?= html_escape($unit_success) ?></div>
<?php endif; ?>
<?php if ($unit_error): ?>
  <div class="alert alert-error"><?= html_escape($unit_error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">i</div>
  <div>
    <strong>Daftar perangkat daerah untuk filter laporan</strong>
    <p>Perangkat daerah yang ditambahkan akan langsung dikunci. Data tidak dapat diedit atau dihapus, tetapi dapat ditampilkan atau disembunyikan dari seluruh pilihan filter.</p>
  </div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/dashboard') ?>">Lihat dashboard</a>
</section>

<?php if ($is_superadmin): ?>
  <section class="panel" style="margin-top:18px">
    <div class="panel-head">
      <div>
        <h2>Tambah perangkat daerah</h2>
        <p>Masukkan nama resmi dan lengkap agar mudah dikenali pengguna.</p>
      </div>
    </div>
    <form method="post" action="<?= site_url('admin/units') ?>">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <input type="hidden" name="action" value="add">
      <div class="question-config-grid">
        <div class="field full">
          <label for="unit_name">Nama perangkat daerah <span class="required-mark">*</span></label>
          <input id="unit_name" name="unit_name" maxlength="255" required placeholder="Contoh: DINAS KESEHATAN PROVINSI JAWA BARAT">
          <small class="field-help"><b>Petunjuk:</b> Periksa penulisan sebelum menyimpan. Setelah ditambahkan, nama tidak dapat diedit atau dihapus.</small>
        </div>
      </div>
      <button class="btn btn-primary btn-sm" type="submit">Tambah perangkat daerah</button>
    </form>
  </section>
<?php else: ?>
  <div class="alert question-readonly-note" style="margin-top:18px">Anda dapat melihat daftar perangkat daerah. Penambahan dan pengaturan tampilan hanya dapat dilakukan oleh superadmin.</div>
<?php endif; ?>

<section class="panel" style="margin-top:18px">
  <div class="panel-head">
    <div>
      <h2>Daftar perangkat daerah</h2>
      <p>Nama seluruh perangkat daerah dikunci. Gunakan tombol status untuk mengatur kemunculannya pada filter.</p>
    </div>
    <span class="badge"><?= count($units) ?> perangkat daerah</span>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:70px">No.</th>
          <th>Nama perangkat daerah</th>
          <th style="width:150px">Jenis izin</th>
          <th style="width:130px">Status</th>
          <th style="width:180px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($units as $index => $unit): ?>
          <tr>
            <td><?= (int) $index + 1 ?></td>
            <td>
              <strong><?= html_escape($unit['n_unitkerja']) ?></strong>
              <?php if (!empty($unit['is_default'])): ?><br><span class="badge">Bawaan sistem</span><?php endif; ?>
            </td>
            <td><?= number_format((int) $unit['service_count']) ?> jenis izin</td>
            <td>
              <?php if (!empty($unit['is_visible'])): ?>
                <span class="badge">Ditampilkan</span>
              <?php else: ?>
                <span class="badge" style="background:#f2f4f7;color:#667085">Disembunyikan</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($is_superadmin): ?>
                <form method="post" action="<?= site_url('admin/units') ?>">
                  <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="unit_id" value="<?= (int) $unit['id'] ?>">
                  <input type="hidden" name="is_visible" value="<?= !empty($unit['is_visible']) ? '0' : '1' ?>">
                  <button class="btn btn-secondary btn-sm" type="submit">
                    <?= !empty($unit['is_visible']) ? 'Sembunyikan' : 'Tampilkan' ?>
                  </button>
                </form>
              <?php else: ?>
                <span style="color:#667085">Nama dikunci</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
