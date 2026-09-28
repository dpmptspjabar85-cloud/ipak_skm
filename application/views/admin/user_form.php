<?php
/** @var int|string $mode */
/** @var array $roles */
/** @var array|null $user */

$isEdit = $mode === 'edit';
$old = $this->session->flashdata('user_create_old');
if (!$old) {
    $old = [];
}
?>

<div class="card">
  <div class="card-head">
    <h2><?= $isEdit ? 'Edit Akun Pengguna' : 'Buat Akun Pengguna Baru' ?></h2>
  </div>

  <form method="post" action="<?= $isEdit ? site_url('admin/users/edit/' . (int) $user['id']) : site_url('admin/users/create') ?>">
    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" required maxlength="100"
        value="<?= html_escape($isEdit ? $user['username'] : (isset($old['username']) ? $old['username'] : '')) ?>"
        <?= $isEdit ? 'readonly' : '' ?>>
      <small class="field-help">Huruf besar, angka, dan garis bawah. Minimal 3 karakter.</small>
    </div>

    <div class="field">
      <label for="nama">Nama Lengkap</label>
      <input id="nama" name="nama" type="text" required maxlength="200"
        value="<?= html_escape($isEdit ? $user['nama'] : (isset($old['nama']) ? $old['nama'] : '')) ?>">
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" maxlength="255"
        value="">
      <small class="field-help"><?= $isEdit ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 6 karakter.' ?></small>
    </div>

    <div class="field">
      <label for="role_name">Role</label>
      <select id="role_name" name="role_name">
        <?php foreach ($roles as $value => $label): ?>
          <option value="<?= $value ?>"
            <?php
            $selectedRole = $isEdit ? $user['role_name'] : (isset($old['role_name']) ? $old['role_name'] : '');
            if ($selectedRole === $value) echo 'selected';
            ?>>
            <?= html_escape($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if ($isEdit): ?>
      <div class="field">
        <label>
          <input type="checkbox" name="is_active" value="1"
            <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>>
          Aktif
        </label>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Simpan' : 'Buat' ?></button>
      <a class="btn btn-secondary" href="<?= site_url('admin/users') ?>">Batal</a>
    </div>
  </form>
</div>