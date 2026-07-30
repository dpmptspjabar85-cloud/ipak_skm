<?php
$queryFilters = array_filter($filters, function ($value) {
    return $value !== '' && $value !== 0 && $value !== null;
});
$exportUrl = site_url('admin/export') . ($queryFilters ? '?' . http_build_query($queryFilters) : '');
$showSurveyTypeFilter = count($survey_types) > 1;
$showSurveyFilter = count($surveys) > 1;
$showGenderFilter = count($genders) > 1;
$showEducationFilter = count($education) > 1;
$showJobFilter = count($jobs) > 1;
$showServiceFilter = count($services) > 0;
$showUnitFilter = count($units) > 0;
?>
<?php if ($has_filter_data): ?>
<form class="filter-card" method="get" action="<?= site_url('admin/responses') ?>">
  <div class="filter-grid">
    <?php if ($showSurveyTypeFilter): ?>
    <div>
      <label for="survey_type">Jenis data</label>
      <select id="survey_type" name="survey_type">
        <option value="">Semua jenis</option>
        <?php foreach ($survey_types as $value => $label): ?>
          <option value="<?= html_escape($value) ?>" <?= $filters['survey_type'] === $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Hanya jenis data yang mempunyai respons pada periode terpilih.</small>
    </div>
    <?php elseif ($filters['survey_type'] !== ''): ?>
      <input type="hidden" name="survey_type" value="<?= html_escape($filters['survey_type']) ?>">
    <?php endif; ?>

    <?php if ($showSurveyFilter): ?>
    <div>
      <label for="survey_id">Ukuran survei</label>
      <select id="survey_id" name="survey_id">
        <option value="">Semua / Gabungan</option>
        <?php foreach ($surveys as $value => $survey): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['survey_id'] === (int) $value ? 'selected' : '' ?>><?= html_escape($survey['index_label']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Hanya survei yang mempunyai hasil pada periode terpilih.</small>
    </div>
    <?php elseif ((int) $filters['survey_id'] > 0): ?>
      <input type="hidden" name="survey_id" value="<?= (int) $filters['survey_id'] ?>">
    <?php endif; ?>

    <div>
      <label for="keyword">Pencarian</label>
      <input id="keyword" name="keyword" type="search" value="<?= html_escape($filters['keyword']) ?>" placeholder="Referensi, kode survei/grup, nama, email">
      <small class="filter-help">Cari menggunakan referensi, UUID survei, kode kelompok pengisian, atau informasi responden.</small>
    </div>
    <div>
      <label for="date_from">Dari tanggal</label>
      <input id="date_from" name="date_from" type="date" value="<?= html_escape($filters['date_from']) ?>">
      <small class="filter-help">Tanggal awal periode pengisian.</small>
    </div>
    <div>
      <label for="date_to">Sampai tanggal</label>
      <input id="date_to" name="date_to" type="date" value="<?= html_escape($filters['date_to']) ?>">
      <small class="filter-help">Tanggal akhir periode pengisian.</small>
    </div>

    <?php if ($showGenderFilter): ?>
    <div>
      <label for="gender">Jenis kelamin</label>
      <select id="gender" name="gender">
        <option value="">Semua</option>
        <?php foreach ($genders as $value => $label): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['gender'] === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Pilihan berasal dari respons yang tersedia.</small>
    </div>
    <?php elseif ((int) $filters['gender'] > 0): ?>
      <input type="hidden" name="gender" value="<?= (int) $filters['gender'] ?>">
    <?php endif; ?>

    <?php if ($showEducationFilter): ?>
    <div>
      <label for="education">Pendidikan</label>
      <select id="education" name="education">
        <option value="">Semua</option>
        <?php foreach ($education as $value => $label): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['education'] === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Pilihan berasal dari respons yang tersedia.</small>
    </div>
    <?php elseif ((int) $filters['education'] > 0): ?>
      <input type="hidden" name="education" value="<?= (int) $filters['education'] ?>">
    <?php endif; ?>

    <?php if ($showJobFilter): ?>
    <div>
      <label for="job">Pekerjaan</label>
      <select id="job" name="job">
        <option value="">Semua</option>
        <?php foreach ($jobs as $value => $label): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['job'] === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Pilihan berasal dari respons yang tersedia.</small>
    </div>
    <?php elseif ((int) $filters['job'] > 0): ?>
      <input type="hidden" name="job" value="<?= (int) $filters['job'] ?>">
    <?php endif; ?>

    <?php if ($showServiceFilter): ?>
    <div>
      <label for="service">Sektor izin</label>
      <select id="service" name="service">
        <option value="">Semua</option>
        <?php foreach ($services as $value => $label): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['service'] === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Hanya sektor yang terdapat pada respons.</small>
    </div>
    <?php elseif ((int) $filters['service'] > 0): ?>
      <input type="hidden" name="service" value="<?= (int) $filters['service'] ?>">
    <?php endif; ?>

    <?php if ($showUnitFilter): ?>
    <div>
      <label for="unit_id">Perangkat daerah</label>
      <select id="unit_id" name="unit_id">
        <option value="">Semua</option>
        <?php foreach ($units as $value => $label): ?>
          <option value="<?= (int) $value ?>" <?= (int) $filters['unit_id'] === (int) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="filter-help">Hanya perangkat daerah yang terdapat pada respons.</small>
    </div>
    <?php elseif ((int) $filters['unit_id'] > 0): ?>
      <input type="hidden" name="unit_id" value="<?= (int) $filters['unit_id'] ?>">
    <?php endif; ?>

    <div class="filter-actions">
      <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/responses') ?>">Reset</a>
      <a class="btn btn-secondary btn-sm" href="<?= html_escape($exportUrl) ?>">Unduh CSV</a>
      <button class="btn btn-primary btn-sm" type="submit">Terapkan filter</button>
    </div>
  </div>
</form>
<?php endif; ?>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Referensi</th>
        <th>Jenis</th>
        <th>Tanggal</th>
        <th>Responden</th>
        <th>Gender / Usia</th>
        <th>Sektor izin</th>
        <th>Nilai terpilih</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" style="padding:35px;text-align:center;color:#667085">Belum ada respons untuk filter ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <?php $meta = $this->ipak->decode_metadata($row['keterangan']); ?>
        <tr>
          <td>
            <strong><?= html_escape($row['resi']) ?></strong><br>
            <small title="<?= html_escape(isset($row['kode_survei_unik']) ? $row['kode_survei_unik'] : '') ?>" style="color:#667085">UUID: <?= html_escape(!empty($row['kode_survei_unik']) ? substr($row['kode_survei_unik'], 0, 13) . (strlen($row['kode_survei_unik']) > 13 ? '...' : '') : '-') ?></small>
            <?php if (!empty($row['kode_pengisian'])): ?><br><small title="<?= html_escape($row['kode_pengisian']) ?>" style="color:#667085">Grup: <?= html_escape(substr($row['kode_pengisian'], 0, 13)) ?>...</small><?php endif; ?>
          </td>
          <td>
            <span class="badge"><?= html_escape(isset($row['jenis_survei']) ? $row['jenis_survei'] : 'SKM') ?></span><br>
            <small style="color:#667085"><?= html_escape(!empty($row['versi_survei']) ? $row['versi_survei'] : ((int) $row['flag_skm'] === 1 ? 'SKM-LEGACY-10' : '1')) ?></small>
          </td>
          <td><?= html_escape(date('d/m/Y H:i', strtotime($row['tgl_buat'] ?: $row['tgl_pengisian']))) ?></td>
          <td>
            <strong><?= html_escape($row['nama_responden'] ?: 'Tanpa nama') ?></strong><br>
            <span style="color:#667085"><?= html_escape((isset($meta['email']) ? $meta['email'] : $row['responden']) ?: '-') ?></span>
          </td>
          <?php
          $genderLabel = (int) $row['gender'] === 1
              ? 'Laki-laki'
              : ((int) $row['gender'] === 2 ? 'Perempuan' : '-');
          $ageLabel = (int) $row['usia'] > 0 ? (int) $row['usia'] . ' th' : '-';
          ?>
          <td><?= html_escape($genderLabel) ?> · <?= html_escape($ageLabel) ?></td>
          <td><?= html_escape(!empty($meta['sector_name'])
              ? $meta['sector_name']
              : (!empty($meta['service_other'])
                  ? $meta['service_other']
                  : (isset($services[(int) $row['sektor']]) ? $services[(int) $row['sektor']] : '-'))) ?></td>
          <td><span class="badge"><?= number_format((float) $row['rata'], 2) ?></span></td>
          <td><a class="btn btn-secondary btn-sm" href="<?= site_url('admin/responses/' . rawurlencode($row['response_key'])) ?>">Detail</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pagination">
  <span>Menampilkan <?= count($rows) ?> dari <?= number_format($total) ?> respons</span>
  <div class="pagination-links">
    <?php if ($page > 1): ?>
      <?php $prevQuery = array_merge($queryFilters, ['page' => $page - 1]); ?>
      <a href="<?= site_url('admin/responses') . '?' . html_escape(http_build_query($prevQuery)) ?>">← Sebelumnya</a>
    <?php endif; ?>
    <span style="padding:8px 4px">Halaman <?= (int) $page ?> / <?= (int) $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <?php $nextQuery = array_merge($queryFilters, ['page' => $page + 1]); ?>
      <a href="<?= site_url('admin/responses') . '?' . html_escape(http_build_query($nextQuery)) ?>">Berikutnya →</a>
    <?php endif; ?>
  </div>
</div>
