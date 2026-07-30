<?php
$jobLabel = ((int) $row['pekerjaan_id'] === 5 && !empty($metadata['job_other']))
    ? $metadata['job_other']
    : (isset($jobs[(int) $row['pekerjaan_id']]) ? $jobs[(int) $row['pekerjaan_id']] : '-');
$serviceLabel = !empty($metadata['sector_name'])
    ? $metadata['sector_name']
    : (!empty($metadata['service_other'])
        ? $metadata['service_other']
        : (isset($services[(int) $row['sektor']]) ? $services[(int) $row['sektor']] : '-'));
?>
<div style="margin-bottom:16px">
  <a class="admin-link" href="<?= site_url('admin/responses') ?>">← Kembali ke data respons</a>
</div>

<div class="detail-grid">
  <section class="panel" style="margin-top:0">
    <div class="panel-head">
      <div>
        <h2>Identitas respons</h2>
        <p><?= html_escape($row['resi']) ?></p>
      </div>
      <span class="badge"><?= html_escape($category['label']) ?></span>
    </div>
    <dl class="detail-list">
      <dt>Jenis survei</dt><dd><span class="badge"><?= html_escape(isset($row['jenis_survei']) ? $row['jenis_survei'] : 'SKM') ?></span></dd>
      <dt>Kode unik survei</dt><dd><code style="word-break:break-all"><?= html_escape(!empty($row['kode_survei_unik']) ? $row['kode_survei_unik'] : '-') ?></code></dd>
      <dt>Versi survei</dt><dd><?= html_escape(!empty($row['versi_survei']) ? $row['versi_survei'] : ((int) $row['flag_skm'] === 1 ? 'SKM-LEGACY-10' : '-')) ?></dd>
      <dt>Kode kelompok pengisian</dt><dd><code style="word-break:break-all"><?= html_escape(!empty($row['kode_pengisian']) ? $row['kode_pengisian'] : '-') ?></code></dd>
      <dt>Tanggal pengisian</dt><dd><?= html_escape(date('d/m/Y H:i', strtotime($row['tgl_buat'] ?: $row['tgl_pengisian']))) ?></dd>
      <dt>Nama/perusahaan</dt><dd><?= html_escape($row['nama_responden'] ?: '-') ?></dd>
      <dt>Telepon</dt><dd><?= html_escape($row['mobile'] ?: '-') ?></dd>
      <dt>Email</dt><dd><?= html_escape((isset($metadata['email']) ? $metadata['email'] : $row['responden']) ?: '-') ?></dd>
      <dt>Nomor identitas / NIB</dt><dd><?= html_escape($row['nib'] ?: '-') ?></dd>
      <dt>Jenis kelamin</dt><dd><?= (int) $row['gender'] === 1 ? 'Laki-laki' : ((int) $row['gender'] === 2 ? 'Perempuan' : '-') ?></dd>
      <dt>Usia</dt><dd><?= (int) $row['usia'] > 0 ? (int) $row['usia'] . ' tahun' : '-' ?></dd>
      <dt>Pendidikan</dt><dd><?= html_escape(isset($education[(int) $row['pendidikan_id']]) ? $education[(int) $row['pendidikan_id']] : '-') ?></dd>
      <dt>Pekerjaan</dt><dd><?= html_escape($jobLabel) ?></dd>
      <dt>Sektor izin</dt><dd><?= html_escape($serviceLabel) ?></dd>
    </dl>
  </section>

  <section class="panel" style="margin-top:0">
    <div class="panel-head">
      <div>
        <h2>Ringkasan nilai</h2>
        <p>Hasil dari <?= count($response_answers) ?> indikator pada form</p>
      </div>
    </div>
    <?php if ($survey_results): ?>
      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
        <?php foreach ($survey_results as $result): ?>
          <div class="stat-card" style="border:0;background:#f7f8fc">
            <span><?= html_escape($result['index_label']) ?></span>
            <strong style="color:<?= html_escape($result['color']) ?>"><?= number_format((float) $result['score'], 2) ?></strong>
            <small><?= html_escape($result['category_label']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="stat-card" style="border:0;background:#f7f8fc">
        <span>Nilai respons</span>
        <strong style="color:<?= html_escape($category['color']) ?>"><?= number_format((float) $row['rata'], 2) ?></strong>
        <small><?= html_escape($category['label']) ?></small>
      </div>
    <?php endif; ?>
    <div style="margin-top:20px">
      <span style="display:block;margin-bottom:7px;color:#667085;font-size:11px;font-weight:800;text-transform:uppercase">Saran responden</span>
      <p style="margin:0;line-height:1.65;font-size:13px"><?= $row['saran'] ? nl2br(html_escape($row['saran'])) : '<span style="color:#98a2b3">Tidak ada saran.</span>' ?></p>
    </div>
  </section>
</div>

<?php if ($response_fields): ?>
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2>Data tambahan dari form</h2>
        <p>Kolom fleksibel disimpan sesuai label yang digunakan saat respons dikirim.</p>
      </div>
      <span class="badge"><?= count($response_fields) ?> kolom</span>
    </div>
    <dl class="detail-list flexible-response-list">
      <?php foreach ($response_fields as $responseField): ?>
        <dt><?= html_escape($responseField['field_label_snapshot']) ?></dt>
        <dd><?= $responseField['field_value'] !== '' ? nl2br(html_escape($responseField['field_value'])) : '-' ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Jawaban per indikator</h2>
      <p>Nilai dan kategori disimpan sebagai snapshot saat respons dikirim.</p>
    </div>
  </div>
  <div class="answer-list">
    <?php foreach ($response_answers as $answer): ?>
      <article class="answer-row">
        <p>
          <strong><?= html_escape($answer['question_code']) ?>.</strong>
          <?= html_escape($answer['question_text_snapshot']) ?>
          <br><small><?= html_escape($answer['measurement_snapshot']) ?> · <?= html_escape($answer['category_snapshot']) ?></small>
        </p>
        <strong>
          <?= html_escape($answer['option_label_snapshot']) ?>
          · Nilai <?= number_format((float) $answer['normalized_score'], 2) ?>
        </strong>
      </article>
    <?php endforeach; ?>
  </div>
</section>
