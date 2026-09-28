<?php
/** @var array $users */
/** @var array $roles */
/** @var string|null $user_success */
/** @var string|null $user_error */
?>

<div class="card">
  <div class="card-head">
    <h2>Daftar Pengguna Backoffice</h2>
    <p>Kelola akun pengguna sistem.</p>
  </div>

  <?php if ($user_success): ?>
    <div class="alert alert-success"><?= html_escape($user_success) ?></div>
  <?php endif; ?>
  <?php if ($user_error): ?>
    <div class="alert alert-error"><?= html_escape($user_error) ?></div>
  <?php endif; ?>

  <div class="card-actions">
    <a class="btn btn-primary" href="<?= site_url('admin/users/create') ?>">+ Tambah Akun</a>
  </div>

  <table class="data-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Username</th>
        <th>Nama</th>
        <th>Role</th>
        <th>Aktif</th>
        <th>Terakhir Masuk</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= (int) $user['id'] ?></td>
          <td><?= html_escape($user['username']) ?></td>
          <td><?= html_escape($user['nama']) ?></td>
          <td>
            <?php if (isset($roles[$user['role_name']])): ?>
              <?= html_escape($roles[$user['role_name']]) ?>
            <?php else: ?>
              <?= html_escape($user['role_name']) ?>
            <?php endif; ?>
          </td>
          <td><?= (int) $user['is_active'] ? 'Ya' : 'Tidak' ?></td>
          <td><?= !empty($user['last_login']) ? html_escape($user['last_login']) : '-' ?></td>
          <td>
            <a class="btn btn-secondary btn-xs" href="<?= site_url('admin/users/edit/' . (int) $user['id']) ?>">Edit</a>
            <form method="post" action="<?= site_url('admin/users/delete/' . (int) $user['id']) ?>" style="display:inline"
              onsubmit="return confirm('Yakin ingin menghapus akun ini?')">
              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
              <button class="btn btn-error btn-xs" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (empty($users)): ?>
    <p class="text-muted">Belum ada pengguna.</p>
  <?php endif; ?>
</div>