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
      <h3>Tabel Baru Dibuat (<?= isset($results['tables_created']) ? count($results['tables_created']) : 0 ?>)</h3>
      <?php if (!empty($results['tables_created'])): ?>
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
      <h3>Tabel Sudah Ada (<?= isset($results['tables_existed']) ? count($results['tables_existed']) : 0 ?>)</h3>
      <?php if (!empty($results['tables_existed'])): ?>
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
      <h3>Kolom Baru Ditambahkan (<?= isset($results['columns_added']) ? count($results['columns_added']) : 0 ?>)</h3>
      <?php if (!empty($results['columns_added'])): ?>
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
      <h3>Index Baru Dibuat (<?= isset($results['indexes_created']) ? count($results['indexes_created']) : 0 ?>)</h3>
      <?php if (!empty($results['indexes_created'])): ?>
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
      <h3>Foreign Key Baru Dibuat (<?= isset($results['foreign_keys_created']) ? count($results['foreign_keys_created']) : 0 ?>)</h3>
      <?php if (!empty($results['foreign_keys_created'])): ?>
        <ul>
          <?php foreach ($results['foreign_keys_created'] as $f): ?>
            <li><code><?= html_escape($f) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted">Tidak ada foreign key baru dibuat.</p>
      <?php endif; ?>
    </section>

    <?php if (!empty($results['foreign_keys_skipped'])): ?>
      <section class="result-card" style="border-color:#f1ca78;background:#fffaf0">
        <h3>Foreign Key Dilewati (<?= count($results['foreign_keys_skipped']) ?>)</h3>
        <p>Constraint belum dipasang karena ada data anak yang tidak memiliki baris induk. Data tidak dihapus.</p>
        <ul>
          <?php foreach ($results['foreign_keys_skipped'] as $foreignKey): ?>
            <li><?= html_escape($foreignKey) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="result-card">
      <h3>Struktur Sudah Lengkap</h3>
      <p>Kolom: <?= isset($results['columns_existed']) ? count($results['columns_existed']) : 0 ?> |
         Index: <?= isset($results['indexes_existed']) ? count($results['indexes_existed']) : 0 ?> |
         FK: <?= isset($results['foreign_keys_existed']) ? count($results['foreign_keys_existed']) : 0 ?></p>
    </section>
  </div>

  <div style="margin:16px; text-align:right">
    <a class="btn btn-secondary" href="<?= site_url('admin/sync-database') ?>">Refresh</a>
  </div>
</div>

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