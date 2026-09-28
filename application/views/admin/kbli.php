<?php if ($success): ?>
  <div class="alert question-alert-success"><?= html_escape($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><?= html_escape($error) ?></div>
<?php endif; ?>

<section class="admin-guide-banner">
  <div class="guide-number">K</div>
  <div>
    <strong>Kelola katalog KBLI</strong>
    <p>Tambahkan data satu per satu atau impor CSV. Katalog ini terpisah dari sektor pada data SKM lama.</p>
  </div>
  <span class="badge"><?= number_format((int) $total) ?> baris</span>
</section>

<?php if ($is_superadmin): ?>
<div class="kbli-admin-grid">
  <section class="panel">
    <div class="panel-head"><div><h2>Tambah data KBLI</h2><p>Semua kolom wajib diisi kecuali deskripsi.</p></div></div>
    <form method="post" action="<?= site_url('admin/kbli') ?>" class="kbli-form">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <input type="hidden" name="action" value="create">
      <div class="kbli-fields-grid">
        <label>Kode Gabungan<input name="kode_gabungan" maxlength="32" required value="<?= html_escape(isset($old['kode_gabungan']) ? $old['kode_gabungan'] : '') ?>" placeholder="A01111"></label>
        <label>Kategori<input name="kategori" maxlength="32" required value="<?= html_escape(isset($old['kategori']) ? $old['kategori'] : '') ?>" placeholder="A"></label>
        <label>Kode<input name="kode" maxlength="32" required value="<?= html_escape(isset($old['kode']) ? $old['kode'] : '') ?>" placeholder="01111"></label>
        <label>Digit<input name="digit" type="number" min="1" max="255" required value="<?= html_escape(isset($old['digit']) ? $old['digit'] : '') ?>" placeholder="5"></label>
        <label>Hirarki<input name="hirarki" maxlength="40" required value="<?= html_escape(isset($old['hirarki']) ? $old['hirarki'] : '') ?>" placeholder="Kelompok"></label>
        <label class="field-wide">Sektor BPS<input name="sektor_bps" maxlength="255" required value="<?= html_escape(isset($old['sektor_bps']) ? $old['sektor_bps'] : '') ?>" placeholder="Pertanian, Kehutanan, dan Perikanan"></label>
        <label class="field-wide">Judul<input name="judul" maxlength="255" required value="<?= html_escape(isset($old['judul']) ? $old['judul'] : '') ?>" placeholder="Pertanian Biji-bijian Penghasil Minyak Makan"></label>
        <label class="field-wide">Deskripsi<textarea name="deskripsi" rows="3" placeholder="Keterangan kategori KBLI"><?= html_escape(isset($old['deskripsi']) ? $old['deskripsi'] : '') ?></textarea></label>
      </div>
      <button class="btn btn-primary" type="submit">Tambah KBLI</button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><div><h2>Impor dari CSV</h2><p>Header wajib sesuai nama kolom pada contoh file: Kode Gabungan, Kategori, Kode, Sektor BPS, Judul, Deskripsi, Digit, Hirarki.</p></div></div>
    <div class="kbli-csv-guide">
      <strong>Contoh format file CSV</strong>
      <p>Baris pertama adalah header. Satu baris berikutnya untuk setiap kode KBLI; digit berisi angka dan hirarki menjelaskan tingkat kodenya.</p>
      <pre><code>Kode Gabungan,Kategori,Kode,Sektor BPS,Judul,Deskripsi,Digit,Hirarki
A,A,A,"Pertanian, Kehutanan, dan Perikanan","Pertanian, Kehutanan, dan Perikanan","Kategori ini mencakup kegiatan pertanian, kehutanan, dan perikanan",1,Kategori
A01,A,01,"Pertanian, Kehutanan, dan Perikanan","Pertanian Tanaman Semusim","Golongan pokok ini mencakup kegiatan pertanian tanaman semusim",2,"Golongan Pokok"
A011,A,011,"Pertanian, Kehutanan, dan Perikanan","Pertanian Tanaman Semusim","Golongan ini mencakup kegiatan tanaman semusim",3,Golongan
A0111,A,0111,"Pertanian, Kehutanan, dan Perikanan","Pertanian Serealia","Subgolongan ini mencakup pertanian padi dan serealia",4,Subgolongan
A01111,A,01111,"Pertanian, Kehutanan, dan Perikanan","Pertanian Jagung","Kelompok ini mencakup kegiatan pertanian jagung",5,Kelompok</code></pre>
      <ul>
        <li>Kolom wajib: Kode Gabungan, Kategori, Kode, Sektor BPS, Judul, Deskripsi, Digit, Hirarki.</li>
        <li>Teks yang mengandung koma harus diapit tanda kutip, seperti <code>"Pertanian, Kehutanan, dan Perikanan"</code>.</li>
        <li>Simpan sebagai CSV UTF-8. Kode diperlakukan sebagai teks; jangan hilangkan nol di depan.</li>
        <li>Pemisah koma, titik koma, dan tab dideteksi otomatis.</li>
      </ul>
    </div>
    <form method="post" action="<?= site_url('admin/kbli') ?>" enctype="multipart/form-data" class="kbli-import-form" onsubmit="return confirmKbliImport(this);">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <input type="hidden" name="action" value="import">
      <label>File CSV<input type="file" name="kbli_csv" accept=".csv,text/csv" required><small>Maksimal 10 MB. Kode disimpan sebagai teks agar nol di depan tetap utuh.</small></label>
      <fieldset class="kbli-import-modes">
        <legend>Perlakuan data yang sudah ada</legend>
        <label><input type="radio" name="import_mode" value="append" checked> <span><strong>Pertahankan isi tabel</strong><small>Tambah kode baru dan perbarui baris dengan Kode Gabungan yang sama.</small></span></label>
        <label class="kbli-replace-mode"><input type="radio" name="import_mode" value="replace"> <span><strong>Hapus semua isi tabel lalu impor</strong><small>Ganti seluruh katalog dengan isi CSV ini. Pilihan ini hanya menghapus data di katalog KBLI.</small></span></label>
      </fieldset>
      <label class="kbli-replace-confirm" hidden><input type="checkbox" name="confirm_replace" value="1"> Saya memahami seluruh data KBLI saat ini akan diganti.</label>
      <button class="btn btn-primary" type="submit">Impor CSV</button>
    </form>
  </section>
</div>
<?php else: ?>
  <div class="alert question-readonly-note">Anda dapat melihat katalog KBLI. Tambah, ubah, hapus, dan impor hanya tersedia untuk superadmin.</div>
<?php endif; ?>

<section class="panel kbli-table-panel">
  <form class="kbli-search" method="get" action="<?= site_url('admin/kbli') ?>">
    <label for="kbli-search">Cari data KBLI</label>
    <input id="kbli-search" name="q" value="<?= html_escape($search) ?>" placeholder="Kode, judul, sektor, hirarki">
    <button class="btn btn-secondary btn-sm" type="submit">Cari</button>
    <?php if ($search !== ''): ?><a class="btn btn-secondary btn-sm" href="<?= site_url('admin/kbli') ?>">Reset</a><?php endif; ?>
  </form>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Kode Gabungan</th><th>Kategori</th><th>Kode</th><th>Sektor BPS</th><th>Judul</th><th>Deskripsi</th><th>Digit</th><th>Hirarki</th><?php if ($is_superadmin): ?><th>Aksi</th><?php endif; ?></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="<?= $is_superadmin ? 9 : 8 ?>" class="empty-cell">Data KBLI tidak ditemukan.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><strong><?= html_escape($row['kode_gabungan']) ?></strong></td><td><?= html_escape($row['kategori']) ?></td><td><?= html_escape($row['kode']) ?></td>
            <td><?= html_escape($row['sektor_bps']) ?></td><td><?= html_escape($row['judul']) ?></td><td class="kbli-description"><?= html_escape($row['deskripsi']) ?></td><td><?= (int) $row['digit'] ?></td><td><?= html_escape($row['hirarki']) ?></td>
            <?php if ($is_superadmin): ?><td class="kbli-row-actions"><a class="btn btn-secondary btn-xs" href="<?= site_url('admin/kbli/edit/' . (int) $row['id']) ?>">Ubah</a><form method="post" action="<?= site_url('admin/kbli/delete/' . (int) $row['id']) ?>" onsubmit="return confirm('Hapus data KBLI <?= html_escape($row['kode_gabungan']) ?>?');"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>"><button class="btn btn-danger btn-xs" type="submit">Hapus</button></form></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total_pages > 1): ?>
    <div class="pagination"><span><?= number_format((int) $total) ?> data · Halaman <?= (int) $page ?> / <?= (int) $total_pages ?></span><div class="pagination-links">
      <?php if ($page > 1): ?><a href="<?= site_url('admin/kbli') . '?' . http_build_query(['q' => $search, 'page' => $page - 1]) ?>">← Sebelumnya</a><?php endif; ?>
      <?php if ($page < $total_pages): ?><a href="<?= site_url('admin/kbli') . '?' . http_build_query(['q' => $search, 'page' => $page + 1]) ?>">Berikutnya →</a><?php endif; ?>
    </div></div>
  <?php endif; ?>
</section>

<script>
function confirmKbliImport(form) {
  var replace = form.querySelector('[name="import_mode"]:checked').value === 'replace';
  var confirmation = form.querySelector('[name="confirm_replace"]');
  if (replace && !confirmation.checked) {
    window.alert('Centang konfirmasi sebelum mengganti seluruh isi katalog KBLI.');
    return false;
  }
  return !replace || window.confirm('Semua baris katalog KBLI akan dihapus lalu diganti dengan isi CSV. Lanjutkan?');
}
(function () {
  var form = document.querySelector('.kbli-import-form');
  if (!form) return;
  var replaceRadio = form.querySelector('[value="replace"]');
  var confirmRow = form.querySelector('.kbli-replace-confirm');
  function updateReplaceState() {
    var active = replaceRadio.checked;
    confirmRow.hidden = !active;
    if (!active) confirmRow.querySelector('input').checked = false;
  }
  form.addEventListener('change', updateReplaceState);
  updateReplaceState();
}());
</script>