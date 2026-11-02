<?php
$edit = $f !== null;
$cfg  = config('Repository');
$val  = static fn (string $k, $d = '') => old_value($k, $edit ? ($f[$k] ?? $d) : $d);
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?><?= $edit ? 'Ubah Berkas' : 'Unggah Berkas' ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => $edit ? 'Ubah Berkas' : 'Unggah Berkas', 'subtitle' => 'Lengkapi metadata agar berkas mudah ditemukan.']) ?>
<form method="post" enctype="multipart/form-data" data-once action="<?= $edit ? site_url('files/' . (int) $f['id'] . '/update') : site_url('files/store') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-header">Informasi Berkas</div><div class="card-body">
                <div class="mb-3"><label class="form-label" for="title">Judul <span class="text-danger">*</span></label>
                    <input class="form-control<?= has_error('title') ?>" id="title" name="title" maxlength="255" required value="<?= esc($val('title'), 'attr') ?>"><?= field_error('title') ?></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label" for="category_id">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select<?= has_error('category_id') ?>" id="category_id" name="category_id" required><option value="">— Pilih —</option>
                            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $val('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach; ?></select><?= field_error('category_id') ?></div>
                    <div class="col-md-6 mb-3"><label class="form-label" for="doc_year">Tahun</label>
                        <input class="form-control<?= has_error('doc_year') ?>" id="doc_year" name="doc_year" type="number" min="1900" max="<?= (int) date('Y') + 1 ?>" value="<?= esc($val('doc_year', date('Y')), 'attr') ?>"><?= field_error('doc_year') ?></div>
                </div>
                <div class="mb-3"><label class="form-label" for="author">Penulis / Pemilik</label>
                    <input class="form-control<?= has_error('author') ?>" id="author" name="author" maxlength="150" value="<?= esc($val('author'), 'attr') ?>"><?= field_error('author') ?></div>
                <div class="mb-3"><label class="form-label" for="keywords">Kata Kunci</label>
                    <input class="form-control<?= has_error('keywords') ?>" id="keywords" name="keywords" maxlength="255" placeholder="pisahkan dengan koma" value="<?= esc($val('keywords'), 'attr') ?>"><?= field_error('keywords') ?></div>
                <div class="mb-0"><label class="form-label" for="description">Deskripsi</label>
                    <textarea class="form-control<?= has_error('description') ?>" id="description" name="description" rows="4" maxlength="5000"><?= esc($val('description')) ?></textarea><?= field_error('description') ?></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-header">Berkas</div><div class="card-body">
                <?php if ($edit): ?><div class="small text-muted mb-2">Saat ini: <strong><?= esc($f['original_name']) ?></strong> (<?= esc(format_bytes($f['file_size'])) ?>). Kosongkan bila tidak ingin mengganti.</div><?php endif; ?>
                <input class="form-control<?= has_error('file') ?>" type="file" name="file" <?= $edit ? '' : 'required' ?> data-max-bytes="<?= (int) $cfg->maxUploadKb * 1024 ?>" data-max-label="<?= esc(format_bytes($cfg->maxUploadKb * 1024), 'attr') ?>" accept=".<?= esc(implode(',.', $cfg->allowedExtensions), 'attr') ?>">
                <?= field_error('file') ?>
                <div class="form-text">Maks. <?= esc(format_bytes($cfg->maxUploadKb * 1024)) ?>. Format: <?= esc(strtoupper(implode(', ', $cfg->allowedExtensions))) ?>.</div>
            </div></div>
            <div class="card mb-3"><div class="card-header">Visibilitas</div><div class="card-body">
                <?php foreach ($cfg->visibilities as $k => $label): ?>
                    <div class="form-check mb-2"><input class="form-check-input" type="radio" name="visibility" id="vis-<?= $k ?>" value="<?= $k ?>" <?= $val('visibility', 'umum') === $k ? 'checked' : '' ?>>
                        <label class="form-check-label" for="vis-<?= $k ?>"><?= esc($label) ?></label></div>
                <?php endforeach; ?>
                <?= field_error('visibility') ?>
            </div></div>
            <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i><?= $edit ? 'Simpan Perubahan' : 'Unggah' ?></button>
                <a class="btn btn-outline-secondary" href="<?= $edit ? site_url('files/' . (int) $f['id']) : site_url('files') ?>">Batal</a>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
