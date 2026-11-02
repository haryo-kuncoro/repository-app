<?php
$edit    = $role !== null;
$posted  = old_value('permissions', null);
$checked = is_array($posted) ? array_map('intval', $posted) : $granted;
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?><?= $edit ? 'Ubah Peran' : 'Tambah Peran' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => $edit ? 'Ubah Peran' : 'Tambah Peran', 'subtitle' => 'Centang hak akses yang diberikan kepada peran ini.']) ?>
<form method="post" data-once action="<?= $edit ? site_url('roles/' . (int) $role['id'] . '/update') : site_url('roles/store') ?>">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body"><div class="row">
        <div class="col-md-5 mb-3 mb-md-0"><label class="form-label" for="name">Nama peran <span class="text-danger">*</span></label><input class="form-control<?= has_error('name') ?>" id="name" name="name" maxlength="60" required value="<?= esc(old_value('name', $edit ? $role['name'] : ''), 'attr') ?>"><?= field_error('name') ?></div>
        <div class="col-md-7"><label class="form-label" for="description">Deskripsi</label><input class="form-control<?= has_error('description') ?>" id="description" name="description" maxlength="255" value="<?= esc(old_value('description', $edit ? (string) $role['description'] : ''), 'attr') ?>"><?= field_error('description') ?></div>
    </div></div></div>

    <div class="row g-3 mb-3">
    <?php $gi = 0; foreach ($groups as $group => $perms): $gi++; ?>
        <div class="col-md-6 col-xl-4"><div class="perm-group">
            <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0"><?= esc($group) ?></h6>
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" data-check-group="g<?= $gi ?>" aria-label="Pilih semua <?= esc($group, 'attr') ?>"></div></div>
            <?php foreach ($perms as $p): $can = in_array((int) $p['id'], $mine, true); ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" id="p<?= (int) $p['id'] ?>" data-group="g<?= $gi ?>" <?= in_array((int) $p['id'], $checked, true) ? 'checked' : '' ?> <?= $can ? '' : 'disabled' ?>>
                    <label class="form-check-label small" for="p<?= (int) $p['id'] ?>"><?= esc($p['label']) ?><br><code class="text-muted"><?= esc($p['name']) ?></code></label>
                </div>
            <?php endforeach; ?>
        </div></div>
    <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button><a class="btn btn-outline-secondary" href="<?= site_url('roles') ?>">Batal</a></div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= asset_url('js/pages.js') ?>"></script><?= $this->endSection() ?>
