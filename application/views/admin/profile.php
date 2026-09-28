<?php
/** @var array $user */
/** @var array $roles */
/** @var string|null $user_success */
/** @var string|null $user_error */
?>

<div class="card">
  <div class="card-head">
    <h2>Akun Saya</h2>
    <p>Info akun dan pengaturan password.</p>
  </div>

  <?php if ($user_success): ?>
    <div class="alert alert-success"><?= html_escape($user_success) ?></div>
  <?php endif; ?>
  <?php if ($user_error): ?>
    <div class="alert alert-error"><?= html_escape($user_error) ?></div>
  <?php endif; ?>

  <div class="info-grid">
    <div class="info-item">
      <label>Username</label>
      <strong><?= html_escape($user['username']) ?></strong>
    </div>
    <div class="info-item">
      <label>Nama</label>
      <strong><?= html_escape($user['nama']) ?></strong>
    </div>
    <div class="info-item">
      <label>Role</label>
      <strong><?= isset($roles[$user['role_name']]) ? html_escape($roles[$user['role_name']]) : html_escape($user['role_name']) ?></strong>
    </div>
    <div class="info-item">
      <label>Terakhir Masuk</label>
      <strong><?= !empty($user['last_login']) ? html_escape($user['last_login']) : '-' ?></strong>
    </div>
    <div class="info-item">
      <label>Status</label>
      <strong><?= (int) $user['is_active'] ? 'Aktif' : 'Non-aktif' ?></strong>
    </div>
  </div>

  <hr>

  <h3>Ganti Password</h3>
  <form method="post" action="<?= site_url('admin/change-password') ?>">
    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

    <div class="field">
      <label for="current_password">Password Saat Ini</label>
      <input id="current_password" name="current_password" type="password" required maxlength="255">
    </div>
    <div class="field">
      <label for="new_password">Password Baru</label>
      <input id="new_password" name="new_password" type="password" required maxlength="255">
      <small class="field-help">Minimal 6 karakter.</small>
    </div>
    <div class="field">
      <label for="confirm_password">Konfirmasi Password Baru</label>
      <input id="confirm_password" name="confirm_password" type="password" required maxlength="255">
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Simpan Password</button>
    </div>
  </form>
</div>