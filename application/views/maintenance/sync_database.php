<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Maintenance - Sinkronisasi Database</title>
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 32px 16px;
        background: #f4f6fb;
        color: #1b2333;
        font: 15px/1.6 -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }
    .wrap { max-width: 960px; margin: 0 auto; }
    .card {
        background: #fff;
        border: 1px solid #dfe4ef;
        border-radius: 12px;
        padding: 28px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(20, 32, 70, .06);
    }
    h1 { margin: 0 0 6px; font-size: 22px; }
    h2 { margin: 24px 0 10px; font-size: 16px; text-transform: uppercase; letter-spacing: .04em; color: #4a5570; }
    .muted { color: #6b7590; margin: 0 0 20px; font-size: 14px; }
    label { display: block; font-weight: 600; margin: 14px 0 6px; font-size: 14px; }
    input[type=text], input[type=password] {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #c9d1e3;
        border-radius: 8px;
        font-size: 15px;
        background: #fbfcfe;
    }
    input:focus { outline: 2px solid #3b62d9; outline-offset: 1px; background: #fff; }
    button {
        margin-top: 20px;
        padding: 11px 22px;
        background: #2f4fbf;
        color: #fff;
        border: 0;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
    }
    button:hover { background: #2743a5; }
    button[disabled] { background: #9aa4bf; cursor: not-allowed; }
    .banner { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
    .banner.ok { background: #e7f6ee; border: 1px solid #a8dcbf; color: #145c37; }
    .banner.bad { background: #fdecec; border: 1px solid #f3b3b3; color: #8f1d1d; }
    .banner.warn { background: #fff6e5; border: 1px solid #f0d49b; color: #7a5310; }
    .banner.info { background: #eaf0fd; border: 1px solid #b9c8f0; color: #24407f; }
    .status-line { font-size: 14px; color: #4a5570; margin: 4px 0 0; }
    ul.list { margin: 0; padding-left: 22px; }
    ul.list li { margin: 2px 0; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; font-size: 13px; }
    .new li::marker { color: #1a7f4b; }
    .old li::marker { color: #97a1b8; }
    .empty { color: #8b94ab; font-style: italic; font-size: 14px; }
    .count { float: right; font-weight: 600; color: #4a5570; }
    table.meta { border-collapse: collapse; width: 100%; font-size: 14px; }
    table.meta td { padding: 5px 0; }
    table.meta td:first-child { width: 190px; color: #6b7590; }

    /* Panel diagnosa */
    details.debug { margin: 0 0 20px; border: 1px solid #dfe4ef; border-radius: 8px; background: #fbfcfe; }
    details.debug summary { cursor: pointer; padding: 10px 14px; font-weight: 600; font-size: 14px; color: #4a5570; }
    table.diag { border-collapse: collapse; width: 100%; font-size: 13px; padding: 0 14px 14px; }
    table.diag td { padding: 4px 8px; border-top: 1px solid #eef1f7; vertical-align: top; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; word-break: break-all; }
    table.diag td:first-child { width: 230px; color: #6b7590; white-space: nowrap; }
    table.diag tr.warn td { background: #fff7e6; color: #7a5310; font-weight: 600; }
    .diag-note { padding: 0 14px 12px; margin: 0; font-size: 13px; color: #7a5310; }
</style>
</head>
<body>
<div class="wrap">

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="banner bad"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($ran && is_array($results)): ?>
    <?php
    $tablesCreated = isset($results['tables_created']) ? $results['tables_created'] : [];
    $tablesExisted = isset($results['tables_existed']) ? $results['tables_existed'] : [];
    $columnsAdded = isset($results['columns_added']) ? $results['columns_added'] : [];
    $columnsExisted = isset($results['columns_existed']) ? $results['columns_existed'] : [];
    $indexesCreated = isset($results['indexes_created']) ? $results['indexes_created'] : [];
    $indexesExisted = isset($results['indexes_existed']) ? $results['indexes_existed'] : [];
    $fkCreated = isset($results['foreign_keys_created']) ? $results['foreign_keys_created'] : [];
    $fkExisted = isset($results['foreign_keys_existed']) ? $results['foreign_keys_existed'] : [];
    $fkSkipped = isset($results['foreign_keys_skipped']) ? $results['foreign_keys_skipped'] : [];
    $syncErrors = isset($results['errors']) ? $results['errors'] : [];
    $dbName = isset($db_name) && $db_name !== '' ? $db_name : '-';
    $succeeded = empty($syncErrors);
    ?>
    <div class="card">
        <?php if ($succeeded): ?>
            <div class="banner ok">SYNC DATABASE BERHASIL</div>
        <?php else: ?>
            <div class="banner bad">SYNC DATABASE GAGAL</div>
        <?php endif; ?>

        <table class="meta">
            <tr><td>Database</td><td><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></td></tr>
            <tr><td>Status</td><td><strong><?= $succeeded ? 'Success' : 'Gagal, sebagian langkah gagal' ?></strong></td></tr>
            <tr><td>Waktu</td><td><?= date('d-m-Y H:i:s') ?></td></tr>
            <tr><td>Tabel dibuat</td><td><?= count($tablesCreated) ?></td></tr>
            <tr><td>Tabel sudah ada</td><td><?= count($tablesExisted) ?></td></tr>
            <tr><td>Field dibuat</td><td><?= count($columnsAdded) ?></td></tr>
            <tr><td>Field sudah ada</td><td><?= count($columnsExisted) ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Tabel</h2>

        <p><strong>Dibuat</strong> <span class="count"><?= count($tablesCreated) ?></span></p>
        <?php if ($tablesCreated): ?>
            <ul class="list new">
                <?php foreach ($tablesCreated as $table): ?>
                    <li><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada tabel baru.</p>
        <?php endif; ?>

        <h2>Sudah ada</h2>
        <?php if ($tablesExisted): ?>
            <ul class="list old">
                <?php foreach ($tablesExisted as $table): ?>
                    <li><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Field yang dibuat</h2>
        <?php if ($columnsAdded): ?>
            <ul class="list new">
                <?php foreach ($columnsAdded as $column): ?>
                    <li><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Semua field sudah tersedia.</p>
        <?php endif; ?>

        <h2>Field yang sudah ada</h2>
        <?php if ($columnsExisted): ?>
            <ul class="list old">
                <?php foreach ($columnsExisted as $column): ?>
                    <li><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Index</h2>
        <p><strong>Dibuat</strong> <span class="count"><?= count($indexesCreated) ?></span></p>
        <?php if ($indexesCreated): ?>
            <ul class="list new">
                <?php foreach ($indexesCreated as $index): ?>
                    <li><?= htmlspecialchars($index, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada index baru.</p>
        <?php endif; ?>

        <p><strong>Sudah ada</strong> <span class="count"><?= count($indexesExisted) ?></span></p>
        <?php if ($indexesExisted): ?>
            <ul class="list old">
                <?php foreach ($indexesExisted as $index): ?>
                    <li><?= htmlspecialchars($index, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Foreign Key</h2>
        <p><strong>Dibuat</strong> <span class="count"><?= count($fkCreated) ?></span></p>
        <?php if ($fkCreated): ?>
            <ul class="list new">
                <?php foreach ($fkCreated as $fk): ?>
                    <li><?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada foreign key baru.</p>
        <?php endif; ?>

        <p><strong>Sudah ada</strong> <span class="count"><?= count($fkExisted) ?></span></p>
        <?php if ($fkExisted): ?>
            <ul class="list old">
                <?php foreach ($fkExisted as $fk): ?>
                    <li><?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">Tidak ada.</p>
        <?php endif; ?>

        <?php if ($fkSkipped): ?>
            <h2>Foreign Key dilewati</h2>
            <?php foreach ($fkSkipped as $skip): ?>
                <div class="banner warn"><?= htmlspecialchars($skip, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Error / Warning</h2>
        <?php if ($syncErrors): ?>
            <?php foreach ($syncErrors as $error): ?>
                <div class="banner bad"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">Tidak ada error maupun warning.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h1>Sinkronisasi Database</h1>
    <p class="muted">Utility maintenance. Jalankan struktur tabel dan field yang diperlukan aplikasi.</p>

<?php if (is_array($debug)): ?>
    <details class="debug">
        <summary>Diagnosa server (<?= count($debug) ?> pemeriksaan)</summary>
        <table class="diag">
<?php
    $needWarn = false;
    foreach ($debug as $label => $value) {
        $bad = preg_match('/TIDAK|gagal|\(kosong\)/', (string) $value);
        if ($bad) {
            $needWarn = true;
        }
        printf(
            '<tr class="%s"><td>%s</td><td>%s</td></tr>',
            $bad ? 'warn' : 'ok',
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
        );
    }
?>
        </table>
<?php if ($needWarn): ?>
        <p class="diag-note">Baris bertanda oranye menunjukkan hal yang perlu diperiksa.</p>
<?php endif; ?>
    </details>
<?php endif; ?>

<?php if ($allowed): ?>
    <div class="banner info">
        Alamat IP <strong><?= htmlspecialchars($client_ip, ENT_QUOTES, 'UTF-8') ?></strong>
        terdeteksi sebagai jaringan internal, sinkronisasi dapat dijalankan.
    </div>

    <form method="post" action="<?= htmlspecialchars(site_url('maintenance/sync_database'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="<?= htmlspecialchars($ci_csrf_name, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($ci_csrf_hash, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="maintenance_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <button type="submit">Sync Database</button>
    </form>

    <p class="status-line">Sinkronisasi hanya berjalan pada pengiriman form di atas. Memuat ulang halaman ini tidak menjalankan sinkronisasi.</p>
<?php else: ?>
    <div class="banner bad">
        Alamat IP <strong><?= htmlspecialchars($client_ip === '' ? 'tidak terbaca' : $client_ip, ENT_QUOTES, 'UTF-8') ?></strong>
        bukan jaringan internal, sinkronisasi dinonaktifkan.
    </div>
    <p class="status-line">Endpoint ini hanya dapat dijalankan dari jaringan internal. Hubungi pengelola server bila Anda berada di jaringan yang seharusnya berwenang.</p>
<?php endif; ?>
</div>

</div>
</body>
</html>
