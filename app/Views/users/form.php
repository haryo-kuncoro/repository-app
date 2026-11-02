<?php
$edit = $u !== null;
$self = $edit && (int) $u['id'] === (int) service('auth')->id();
$posted = old_value('_posted', 0);
$val  = static fn (string $k, $d = '') => old_value($k, $edit ? ($u[$k] ?? $d) : $d);
$flag = static function (string $k, int $default) use ($posted, $edit, $u): bool {
    if ($posted) { return (bool) old_value($k, 0); }
    return $edit ? (bool) ($u[$k] ?? $default) : (bool) $default;
};
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?><?= $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => $edit ? 'Ubah Pengguna' : 'Tambah Pengguna']) ?>
<div class="row"><div class="col-lg-7"><div class="card"><div class="card-body">
<form method="post" data-once autocomplete="off" action="<?= $edit ? site_url('users/' . (int) $u['id'] . '/update') : site_url('users/store') ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="name">Nama <span class="text-danger">*</span></label><input class="form-control<?= has_error('name') ?>" id="name" name="name" maxlength="100" required value="<?= esc($val('name'), 'attr') ?>"><?= field_error('name') ?></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label" for="username">Username <span class="text-danger">*</span></label><input class="form-control<?= has_error('username') ?>" id="username" name="username" maxlength="30" required value="<?= esc($val('username'), 'attr') ?>"><?= field_error('username') ?></div>
        <div class="col-md-6 mb-3"><label class="form-label" for="email">Email <span class="text-danger">*</span></label><input class="form-control<?= has_error('email') ?>" id="email" name="email" type="email" maxlength="150" required value="<?= esc($val('email'), 'attr') ?>"><?= field_error('email') ?></div>
    </div>
    <div class="mb-3"><label class="form-label" for="role_id">Peran <span class="text-danger">*</span></label>
        <select class="form-select<?= has_error('role_id') ?>" id="role_id" name="role_id" required <?= $self ? 'disabled' : '' ?>>
            <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $val('role_id') === (int) $r['id'] ? 'selected' : '' ?>><?= esc($r['name']) ?></option><?php endforeach; ?>
            <?php if ($self): ?><option selected value="<?= (int) $u['role_id'] ?>"><?= esc($u['role_slug']) ?></option><?php endif; ?>
        </select><?= field_error('role_id') ?>
        <?php if ($self): ?><div class="form-text">Anda tidak dapat mengubah peran akun sendiri.</div><?php endif; ?></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label" for="password">Password <?= $edit ? '(kosongkan bila tidak diubah)' : '<span class="text-danger">*</span>' ?></label>
            <input class="form-control<?= has_error('password') ?>" id="password" name="password" type="password" maxlength="72" autocomplete="new-password" <?= $edit ? '' : 'required' ?>><?= field_error('password') ?></div>
        <div class="col-md-6 mb-3"><label class="form-label" for="password_confirm">Ulangi Password</label>
            <input class="form-control<?= has_error('password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" maxlength="72" autocomplete="new-password"><?= field_error('password_confirm') ?></div>
    </div>
    <?php if (! $self): ?>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $flag('is_active', 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Akun aktif</label></div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="mcp" name="must_change_password" value="1" <?= $flag('must_change_password', 1) ? 'checked' : '' ?>><label class="form-check-label" for="mcp">Wajib ganti password saat login berikutnya</label></div>
    <?php endif; ?>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button><a class="btn btn-outline-secondary" href="<?= site_url('users') ?>">Batal</a></div>
</form></div></div></div></div>
<?= $this->endSection() ?>
