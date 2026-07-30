<?php
$activeCount = 0;
$requests24h = 0;
foreach ($clients as $clientItem) {
    if ($clientItem['is_active']) {
        $activeCount++;
    }
    $requests24h += (int) $clientItem['request_count_24h'];
}
$surveyNames = [];
foreach ($surveys as $surveyId => $survey) {
    $surveyNames[(int) $surveyId] = $survey['survey_name'];
}
?>

<?php if ($success): ?>
  <div class="alert access-message-success"><?= html_escape($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><?= html_escape($error) ?></div>
<?php endif; ?>

<?php if ($credentials): ?>
  <?php
  $curlExample = 'curl -H "Authorization: Bearer ' . $credentials['api_key'] . '" "' .
      $credentials['endpoint'] . '?resource=summary&year=' . date('Y') . '"';
  ?>
  <section class="api-credential-card" aria-labelledby="api-credential-title">
    <div>
      <span class="step-kicker">Tampilkan satu kali</span>
      <h2 id="api-credential-title">Kredensial <?= html_escape($credentials['client_name']) ?></h2>
      <p>Salin endpoint dan kunci sekarang. Demi keamanan, kunci lengkap tidak disimpan dan tidak dapat dilihat kembali.</p>
    </div>
    <div class="api-copy-field">
      <label for="new-api-endpoint">Endpoint API</label>
      <div>
        <input id="new-api-endpoint" type="text" readonly value="<?= html_escape($credentials['endpoint']) ?>">
        <button class="btn btn-secondary btn-sm api-copy-button" type="button" data-copy-target="new-api-endpoint">Salin</button>
      </div>
    </div>
    <div class="api-copy-field">
      <label for="new-api-key">Kunci API</label>
      <div>
        <input id="new-api-key" type="text" readonly value="<?= html_escape($credentials['api_key']) ?>">
        <button class="btn btn-secondary btn-sm api-copy-button" type="button" data-copy-target="new-api-key">Salin</button>
      </div>
    </div>
    <div class="api-copy-field">
      <label for="new-api-example">Contoh permintaan</label>
      <div>
        <input id="new-api-example" type="text" readonly value="<?= html_escape($curlExample) ?>">
        <button class="btn btn-secondary btn-sm api-copy-button" type="button" data-copy-target="new-api-example">Salin</button>
      </div>
    </div>
    <?php if (!empty($credentials['client_id'])): ?>
      <div class="api-credential-actions">
        <a class="btn btn-primary btn-sm" href="<?= site_url('admin/api-builder/documentation/' . (int) $credentials['client_id']) ?>">Unduh dokumentasi PDF</a>
        <small>PDF tidak memuat kunci lengkap. Simpan kunci di tempat rahasia dan kirim secara terpisah.</small>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="api-builder-intro">
  <div>
    <span class="step-kicker">Integrasi data resmi</span>
    <h2>Bagikan hanya data yang diperlukan.</h2>
    <p>Setiap aplikasi peminta memperoleh endpoint dan kunci berbeda. Pilih survei, keluaran, kolom detail, dimensi grafik, serta batas aksesnya tanpa membuka seluruh database. Setelah akses tersimpan, dokumentasi PDF resmi dapat diunduh dari kartu peminta.</p>
  </div>
  <ol>
    <li><b>1</b><span>Tentukan aplikasi peminta dan survei yang boleh dibaca.</span></li>
    <li><b>2</b><span>Pilih keluaran ringkasan, grafik, detail, atau pertanyaan.</span></li>
    <li><b>3</b><span>Berikan endpoint dan kunci API kepada pengelola aplikasi.</span></li>
  </ol>
  <a class="btn btn-primary" href="<?= site_url('admin/api-builder/create') ?>">Buat akses API baru</a>
</section>

<section class="stats-grid api-stats-grid" aria-label="Ringkasan akses API">
  <article class="stat-card">
    <span>Total peminta API</span>
    <strong><?= number_format(count($clients)) ?></strong>
    <small>Konfigurasi yang pernah dibuat</small>
  </article>
  <article class="stat-card">
    <span>Akses aktif</span>
    <strong><?= number_format($activeCount) ?></strong>
    <small>Dapat menerima permintaan saat ini</small>
  </article>
  <article class="stat-card">
    <span>Permintaan 24 jam</span>
    <strong><?= number_format($requests24h) ?></strong>
    <small>Seluruh endpoint terdaftar</small>
  </article>
</section>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Panduan permintaan API</h2>
      <p>API menggunakan metode GET dan mengirim JSON. Kunci harus ditempatkan pada header, bukan pada URL.</p>
    </div>
  </div>
  <div class="api-doc-grid">
    <article>
      <span>Header autentikasi</span>
      <code>Authorization: Bearer KUNCI_API</code>
      <small>Alternatif: <code>X-API-Key: KUNCI_API</code></small>
    </article>
    <article>
      <span>Ringkasan per survei</span>
      <code>?resource=summary&amp;survey=SKM&amp;year=<?= date('Y') ?></code>
      <small>Total respons, rata-rata, nilai minimum, maksimum, dan kategori.</small>
    </article>
    <article>
      <span>Data grafik</span>
      <code>?resource=chart&amp;survey=SKM&amp;dimension=gender&amp;year=<?= date('Y') ?></code>
      <small>Dimensi mengikuti pilihan yang diizinkan pada konfigurasi peminta.</small>
    </article>
    <article>
      <span>Detail respons</span>
      <code>?resource=details&amp;survey=SKM&amp;page=1&amp;per_page=25</code>
      <small>Hanya kolom detail yang dicentang pada konfigurasi akan dikirim.</small>
    </article>
    <article>
      <span>Struktur pertanyaan</span>
      <code>?resource=questions&amp;survey=SKM</code>
      <small>Mengirim pertanyaan, pengukuran, kategori, bobot, dan opsi jawaban.</small>
    </article>
    <article>
      <span>Filter tambahan</span>
      <code>date_from, date_to, unit_id, service, gender, education, job</code>
      <small>Filter hanya membatasi data yang memang berada dalam cakupan kunci API.</small>
    </article>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Daftar akses API</h2>
      <p>Nonaktifkan akses apabila kerja sama selesai. Data respons survei tidak ikut terhapus.</p>
    </div>
    <a class="btn btn-primary btn-sm" href="<?= site_url('admin/api-builder/create') ?>">Tambah peminta</a>
  </div>

  <?php if (!$clients): ?>
    <div class="api-empty-state">
      <strong>Belum ada akses API.</strong>
      <p>Buat akses pertama untuk membagikan data survei secara terkontrol.</p>
    </div>
  <?php else: ?>
    <div class="api-client-grid">
      <?php foreach ($clients as $client): ?>
        <?php
        $clientSurveyLabels = [];
        if ($client['survey_scope'] === 'all') {
            $clientSurveyLabels[] = 'Semua survei aktif';
        } else {
            foreach ($client['allowed_survey_ids'] as $surveyId) {
                if (isset($surveyNames[(int) $surveyId])) {
                    $clientSurveyLabels[] = $surveyNames[(int) $surveyId];
                }
            }
        }
        ?>
        <article class="api-client-card">
          <div class="api-client-head">
            <div>
              <span class="badge <?= $client['is_active'] ? 'api-badge-active' : 'api-badge-muted' ?>">
                <?= $client['is_active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
              <h3><?= html_escape($client['client_name']) ?></h3>
              <code><?= html_escape($client['client_code']) ?></code>
            </div>
            <div class="api-client-metric">
              <strong><?= number_format((int) $client['request_count_24h']) ?></strong>
              <span>permintaan / 24 jam</span>
            </div>
          </div>

          <?php if ($client['description'] !== ''): ?>
            <p><?= html_escape($client['description']) ?></p>
          <?php endif; ?>

          <dl class="api-client-details">
            <dt>Survei</dt>
            <dd><?= html_escape($clientSurveyLabels ? implode(', ', $clientSurveyLabels) : 'Belum dipilih') ?></dd>
            <dt>Keluaran</dt>
            <dd>
              <?php foreach ($client['allowed_resources'] as $resource): ?>
                <?php if (isset($resources[$resource])): ?>
                  <span class="badge"><?= html_escape($resources[$resource]['label']) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </dd>
            <dt>Kunci</dt>
            <dd><code><?= html_escape($client['api_key_prefix']) ?>…</code></dd>
            <dt>Terakhir dipakai</dt>
            <dd><?= $client['last_used_at'] ? html_escape(date('d-m-Y H:i', strtotime($client['last_used_at']))) : 'Belum pernah' ?></dd>
            <dt>Masa berlaku</dt>
            <dd><?= $client['expires_at'] ? html_escape(date('d-m-Y H:i', strtotime($client['expires_at']))) : 'Tanpa batas tanggal' ?></dd>
          </dl>

          <div class="api-endpoint-preview">
            <span>Endpoint</span>
            <code><?= html_escape(site_url('api/v1/survey-data/' . $client['client_code'])) ?></code>
          </div>

          <div class="api-client-actions">
            <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/api-builder/edit/' . (int) $client['id']) ?>">Ubah pengaturan</a>
            <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/api-builder/documentation/' . (int) $client['id']) ?>">Dokumentasi PDF</a>
            <form method="post" action="<?= site_url('admin/api-builder/regenerate/' . (int) $client['id']) ?>" onsubmit="return confirm('Buat kunci baru? Kunci lama akan langsung tidak berlaku.');">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Buat ulang kunci</button>
            </form>
            <form method="post" action="<?= site_url('admin/api-builder/toggle/' . (int) $client['id']) ?>">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <input type="hidden" name="is_active" value="<?= $client['is_active'] ? 0 : 1 ?>">
              <button class="btn <?= $client['is_active'] ? 'btn-danger-soft' : 'btn-primary' ?> btn-sm" type="submit">
                <?= $client['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>Aktivitas terbaru</h2>
      <p>Catatan permintaan membantu melihat penggunaan, kesalahan, dan pembatasan akses.</p>
    </div>
  </div>
  <?php if (!$recent_logs): ?>
    <div class="api-empty-state">
      <strong>Belum ada permintaan API.</strong>
      <p>Aktivitas akan muncul setelah aplikasi lain memanggil endpoint.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Peminta</th>
            <th>Keluaran</th>
            <th>Status</th>
            <th>Data</th>
            <th>Durasi</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_logs as $log): ?>
            <tr>
              <td><?= html_escape(date('d-m-Y H:i:s', strtotime($log['requested_at']))) ?></td>
              <td><strong><?= html_escape($log['client_name']) ?></strong><br><small><?= html_escape($log['client_code']) ?></small></td>
              <td><?= html_escape($log['resource_name']) ?></td>
              <td><span class="badge <?= (int) $log['status_code'] < 400 ? 'api-badge-active' : 'api-badge-error' ?>"><?= (int) $log['status_code'] ?></span></td>
              <td><?= number_format((int) $log['response_count']) ?></td>
              <td><?= number_format((int) $log['duration_ms']) ?> ms</td>
              <td><?= html_escape($log['request_ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<script>
(function () {
  var buttons = document.querySelectorAll('.api-copy-button');
  Array.prototype.forEach.call(buttons, function (button) {
    button.addEventListener('click', function () {
      var target = document.getElementById(button.getAttribute('data-copy-target'));
      if (!target) return;
      target.select();
      target.setSelectionRange(0, target.value.length);
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(target.value);
      } else {
        document.execCommand('copy');
      }
      var original = button.textContent;
      button.textContent = 'Tersalin';
      window.setTimeout(function () { button.textContent = original; }, 1400);
    });
  });
})();
</script>
