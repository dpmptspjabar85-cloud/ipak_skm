<?php
$queryFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== 0 && $value !== null;
});
$filterQuery = http_build_query($queryFilters);
?>
<?php if ($has_filter_skm): ?>
<form class="filter-card" method="get" action="<?= site_url('admin/response-skm') ?>">
  <div class="filter-grid">
    <div>
      <label for="keyword">Pencarian</label>
      <input id="keyword" name="keyword" type="search" value="<?= html_escape($filters['keyword']) ?>" placeholder="Nama, NIB, resi, email">
    </div>

    <div>
      <label for="status_responden">Status Responden</label>
      <select id="status_responden" name="status_responden">
        <option value="0">Semua status</option>
        <option value="1" <?= $filters['status_responden'] === 1 ? 'selected' : '' ?>>Pemohon</option>
        <option value="2" <?= $filters['status_responden'] === 2 ? 'selected' : '' ?>>Pengurus Izin</option>
      </select>
    </div>

    <div>
      <label for="jenis_survei">Jenis Survei</label>
      <select id="jenis_survei" name="jenis_survei">
        <option value="">Semua jenis</option>
        <?php foreach ($jenis_survei_labels as $value => $label): ?>
          <option value="<?= html_escape($value) ?>" <?= $filters['jenis_survei'] === $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="date_from">Dari tanggal</label>
      <input id="date_from" name="date_from" type="date" value="<?= html_escape($filters['date_from']) ?>">
    </div>
    <div>
      <label for="date_to">Sampai tanggal</label>
      <input id="date_to" name="date_to" type="date" value="<?= html_escape($filters['date_to']) ?>">
    </div>

    <div class="filter-actions">
      <span class="filter-help">
        Periode aktif: <strong><?= html_escape($filters['date_from']) ?> s.d. <?= html_escape($filters['date_to']) ?></strong>
      </span>
      <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/response-skm') ?>">Reset</a>
      <button class="btn btn-primary btn-sm" type="submit">Terapkan filter</button>
    </div>
  </div>
</form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Kode</th>
        <th>NIB</th>
        <th>Nomor Resi</th>
        <th>Nama Responden</th>
        <th>Status</th>
        <th>Email</th>
        <th>Nomor HP</th>
        <th>Jenis Kelamin</th>
        <th>Usia</th>
        <th>Pekerjaan</th>
        <th>Pendidikan</th>
        <th>Sektor</th>
        <th>Jenis Izin</th>
        <th>Tanggal Pengisian</th>
        <th>Total Nilai</th>
        <th>Rata-rata</th>
        <th>Jenis Survei</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="18" style="padding:35px;text-align:center;color:#667085">Belum ada data responden SKM untuk filter ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $genderLabel = $row['gender_nama'] !== '-' ? $row['gender_nama'] : '-';
          $ageLabel = (int) $row['usia'] > 0 ? (int) $row['usia'] . ' th' : '-';
          $meta = $this->ipak->decode_metadata($row['keterangan']);
          $email = isset($meta['email']) ? $meta['email'] : $row['responden'];
        ?>
        <tr>
          <td><strong><?= html_escape($row['kode']) ?></strong></td>
          <td><?= html_escape($row['nib'] ?: '-') ?></td>
          <td><?= html_escape($row['resi'] ?: '-') ?></td>
          <td><strong><?= html_escape($row['nama_responden'] ?: 'Tanpa nama') ?></strong></td>
          <td>
            <span class="badge <?= $row['status_nama'] === 'Pengurus Izin' ? 'badge-warning' : 'badge-info' ?>">
              <?= html_escape($row['status_nama']) ?>
            </span>
          </td>
          <td><?= html_escape($email ?: '-') ?></td>
          <td><?= html_escape($row['mobile'] ?: '-') ?></td>
          <td><?= html_escape($genderLabel) ?> · <?= html_escape($ageLabel) ?></td>
          <td><?= html_escape($row['pekerjaan_nama']) ?></td>
          <td><?= html_escape($row['pendidikan_nama']) ?></td>
          <td><?= html_escape($row['sektor_nama']) ?></td>
          <td><?= html_escape($row['jenis_ijin'] ?: '-') ?></td>
          <td><?= html_escape(date('d/m/Y H:i', strtotime($row['tgl_buat'] ?: $row['tgl_pengisian']))) ?></td>
          <td><?= number_format((float) $row['total'], 2) ?></td>
          <td><span class="badge badge-primary"><?= number_format((float) $row['rata'], 2) ?></span></td>
          <td><?= html_escape($row['jenis_survei_label']) ?></td>
          <td>
            <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/response-skm/detail/' . (int) $row['kode']) ?>">Detail</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pagination">
  <span>Menampilkan <?= count($rows) ?> dari <?= number_format($total) ?> responden SKM</span>
  <div class="pagination-links">
    <?php if ($page > 1): ?>
      <?php $prevQuery = array_merge($filters, ['page' => $page - 1]); ?>
      <a href="<?= site_url('admin/response-skm') . '?' . html_escape(http_build_query($prevQuery)) ?>">← Sebelumnya</a>
    <?php endif; ?>
    <span style="padding:8px 4px">Halaman <?= (int) $page ?> / <?= (int) $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <?php $nextQuery = array_merge($filters, ['page' => $page + 1]); ?>
      <a href="<?= site_url('admin/response-skm') . '?' . html_escape(http_build_query($nextQuery)) ?>">Berikutnya →</a>
    <?php endif; ?>
  </div>
</div>
