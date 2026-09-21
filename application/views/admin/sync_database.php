<?php if (isset($results)): ?>
  <div class="panel" style="margin-top:18px">
    <div class="panel-head">
      <h2>Hasil Sinkronisasi Database</h2>
      <span class="badge"><?= date('Y-m-d H:i:s') ?></span>
    </div>

    <?php if (!empty($results['errors'])): ?>
      <div class="alert alert-error" style="margin:16px">
        <strong>Ditemukan error:</strong>
        <ul style="margin:8px 0 0 20px">
          <?php foreach ($results['errors'] as $err): ?>
            <li><?= html_escape($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px; margin:16px">
      <section class="result-card">
        <h3>Tabel Baru Dibuat (<?= count($results['tables_created']) ?>)</h3>
        <?php if ($results['tables_created']): ?>
          <ul>
            <?php foreach ($results['tables_created'] as $t): ?>
              <li><code><?= html_escape($t) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">Tidak ada tabel baru dibuat.</p>
        <?php endif; ?>
      </section>

      <section class="result-card">
        <h3>Tabel Sudah Ada (<?= count($results['tables_existed']) ?>)</h3>
        <?php if ($results['tables_existed']): ?>
          <ul>
            <?php foreach ($results['tables_existed'] as $t): ?>
              <li><code><?= html_escape($t) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">Semua tabel dibutuhkan sudah dibuat.</p>
        <?php endif; ?>
      </section>

      <section class="result-card">
        <h3>Kolom Baru Ditambahkan (<?= count($results['columns_added']) ?>)</h3>
        <?php if ($results['columns_added']): ?>
          <ul>
            <?php foreach ($results['columns_added'] as $c): ?>
              <li><code><?= html_escape($c) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">Tidak ada kolom baru ditambahkan.</p>
        <?php endif; ?>
      </section>

      <section class="result-card">
        <h3>Index Baru Dibuat (<?= count($results['indexes_created']) ?>)</h3>
        <?php if ($results['indexes_created']): ?>
          <ul>
            <?php foreach ($results['indexes_created'] as $i): ?>
              <li><code><?= html_escape($i) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">Tidak ada index baru dibuat.</p>
        <?php endif; ?>
      </section>

      <section class="result-card">
        <h3>Foreign Key Baru Dibuat (<?= count($results['foreign_keys_created']) ?>)</h3>
        <?php if ($results['foreign_keys_created']): ?>
          <ul>
            <?php foreach ($results['foreign_keys_created'] as $f): ?>
              <li><code><?= html_escape($f) ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">Tidak ada foreign key baru dibuat.</p>
        <?php endif; ?>
      </section>

      <section class="result-card">
        <h3>Struktur Sudah Lengkap</h3>
        <p>Kolom: <?= count($results['columns_existed']) ?> | Index: <?= count($results['indexes_existed']) ?> | FK: <?= count($results['foreign_keys_existed']) ?></p>
      </section>
    </div>

    <div style="margin:16px; text-align:right">
      <a class="btn btn-secondary" href="<?= site_url('admin/sync-database') ?>">Kembali</a>
    </div>
  </div>
<?php else: ?>
  <section class="admin-guide-banner">
    <div class="guide-number">i</div>
    <div>
      <strong>Sinkronisasi Database</strong>
      <p>Fitur ini membandingkan struktur database saat ini dengan struktur yang dibutuhkan aplikasi, lalu menambahkan tabel, kolom, index, dan foreign key yang hilang. Proses ini aman untuk dijalankan berulang kali.</p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Sinkronisasi Database</h2>
    </div>
    <div style="padding:20px">
      <form method="post" action="<?= site_url('admin/sync-database') ?>" id="sync-form">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <p>Klik tombol di bawah untuk memulai sinkronisasi. Proses ini akan:</p>
        <ul>
          <li>Memeriksa keberadaan tabel yang dibutuhkan</li>
          <li>Membuat tabel yang belum ada</li>
          <li>Menambahkan kolom yang hilang pada tabel yang sudah ada</li>
          <li>Membuat index dan foreign key yang diperlukan</li>
        </ul>
        <p class="text-muted">Catatan: Proses ini tidak akan menghapus tabel atau kolom yang sudah ada. Hanya menambahkan yang hilang.</p>
        <button class="btn btn-primary" type="submit" id="sync-btn">Mulai Sinkronisasi</button>
      </form>
    </div>
  </section>

  <style>
    .result-card {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
      background: #fafafa;
    }
    .result-card h3 {
      margin-top: 0;
      font-size: 14px;
      color: #334155;
    }
    .result-card ul {
      margin: 8px 0 0 20px;
      padding: 0;
    }
    .result-card li {
      margin: 4px 0;
      font-size: 13px;
    }
    .result-card code {
      background: #e2e8f0;
      padding: 2px 6px;
      border-radius: 4px;
      font-family: monospace;
    }
    .text-muted { color: #64748b; }
  </style>
<?php endif; ?>