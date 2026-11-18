<?php
$meta = $this->ipak->decode_metadata($row['keterangan']);
?>
<div class="detail-card">
  <div class="detail-grid">
    <div class="detail-section">
      <h3>Identitas Responden</h3>
      <table class="detail-table">
        <tr><th>Kode</th><td><?= html_escape($row['kode']) ?></td></tr>
        <tr><th>NIB</th><td><?= html_escape($row['nib'] ?: '-') ?></td></tr>
        <tr><th>Nomor Resi</th><td><?= html_escape($row['resi'] ?: '-') ?></td></tr>
        <tr><th>Nama Lengkap</th><td><?= html_escape($row['nama_responden'] ?: 'Tanpa nama') ?></td></tr>
        <tr><th>Email</th><td><?= html_escape(isset($meta['email']) ? $meta['email'] : $row['responden']) ?: '-' ?></td></tr>
        <tr><th>Nomor HP</th><td><?= html_escape($row['mobile'] ?: '-') ?></td></tr>
        <tr><th>Jenis Kelamin</th><td><?= html_escape($row['gender_nama']) ?></td></tr>
        <tr><th>Usia</th><td><?= (int) $row['usia'] > 0 ? (int) $row['usia'] . ' tahun' : '-' ?></td></tr>
      </table>
    </div>

    <div class="detail-section">
      <h3>Informasi Survei</h3>
      <table class="detail-table">
        <tr><th>Jenis Izin</th><td><?= html_escape($row['jenis_ijin'] ?: '-') ?></td></tr>
        <tr><th>Permohonan ID</th><td><?= html_escape($row['permohonan_id'] ?: '-') ?></td></tr>
        <tr><th>Jenis Survei</th><td><?= html_escape(ucfirst(strtolower(isset($row['jenis_survei']) ? $row['jenis_survei'] : 'SKM'))) ?></td></tr>
        <tr><th>Sektor Izin</th><td><?= html_escape($row['sektor_nama']) ?></td></tr>
        <tr><th>Status Responden</th><td>
          <span class="badge <?= $row['status_nama'] === 'Pengurus Izin' ? 'badge-warning' : 'badge-info' ?>">
            <?= html_escape($row['status_nama']) ?>
          </span>
        </td></tr>
        <tr><th>Pendidikan</th><td><?= html_escape($row['pendidikan_nama']) ?></td></tr>
        <tr><th>Pekerjaan</th><td><?= html_escape($row['pekerjaan_nama']) ?></td></tr>
        <tr><th>Tanggal Pengisian</th><td><?= html_escape(date('d/m/Y H:i', strtotime($row['tgl_buat'] ?: $row['tgl_pengisian']))) ?></td></tr>
      </table>
    </div>

    <div class="detail-section">
      <h3>Nilai & Skor</h3>
      <table class="detail-table">
        <tr><th>Total Nilai</th><td><?= number_format((float) $row['total'], 2) ?></td></tr>
        <tr><th>Rata-rata</th><td><strong><?= number_format((float) $row['rata'], 2) ?></strong></td></tr>
        <?php $category = $this->ipak->survey_score_category(0, (float) $row['rata']); ?>
        <tr><th>Kategori</th><td><?= html_escape($category['label']) ?></td></tr>
      </table>
    </div>
  </div>

  <?php if (!empty($row['saran'])): ?>
    <div class="detail-section">
      <h3>Saran</h3>
      <div class="detail-text">
        <?= nl2br(html_escape($row['saran'])) ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($row['keterangan'])): ?>
    <div class="detail-section">
      <h3>Keterangan</h3>
      <div class="detail-text">
        <?= nl2br(html_escape($row['keterangan'])) ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="detail-actions">
    <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/response-skm') ?>">← Kembali</a>
  </div>
</div>

<style>
.detail-card { max-width: 1000px; margin: 0 auto; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.detail-section h3 { margin: 0 0 10px 0; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 14px; font-weight: 600; }
.detail-table { width: 100%; border-collapse: collapse; }
.detail-table th, .detail-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
.detail-table th { width: 40%; color: #64748b; font-weight: 500; }
.detail-text { padding: 10px; background: #f8fafc; border-radius: 4px; font-size: 13px; line-height: 1.6; }
.detail-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
@media (max-width: 768px) {
  .detail-grid { grid-template-columns: 1fr; }
}
</style>
