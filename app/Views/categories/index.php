<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Kategori<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Kategori Berkas', 'subtitle' => 'Kelompokkan berkas, mis. Skripsi, Akreditasi, Monev.', 'actions' => '<button class="btn btn-primary" type="button" data-category-new><i class="bi bi-plus-lg me-1"></i>Tambah Kategori</button>']) ?>
<div class="card"><div class="card-body"><div class="table-responsive-wrap">
<table class="table table-hover datatable w-100">
    <thead><tr><th>Nama</th><th>Deskripsi</th><th>Berkas</th><th>Status</th><th class="text-end no-sort">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><i class="bi <?= esc($r['icon'], 'attr') ?> text-primary me-2"></i><strong><?= esc($r['name']) ?></strong></td>
            <td class="text-muted"><?= esc($r['description']) ?></td>
            <td><?= (int) $r['file_count'] ?></td>
            <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-soft-teal">Aktif</span>' : '<span class="badge badge-soft-gray">Nonaktif</span>' ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary btn-icon" type="button" title="Ubah" data-category-edit data-id="<?= (int) $r['id'] ?>" data-name="<?= esc($r['name'], 'attr') ?>" data-description="<?= esc((string) $r['description'], 'attr') ?>" data-icon="<?= esc($r['icon'], 'attr') ?>" data-active="<?= (int) $r['is_active'] ?>"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger btn-icon" type="button" title="Hapus" data-post="<?= site_url('categories/' . (int) $r['id'] . '/delete') ?>" data-confirm-title="Hapus kategori?" data-confirm="Kategori “<?= esc($r['name'], 'attr') ?>” akan dihapus." data-confirm-button="Ya, hapus"><i class="bi bi-trash"></i></button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>

<div class="modal fade" id="category-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= site_url('categories/save') ?>" data-once>
    <?= csrf_field() ?><input type="hidden" name="id" id="cat-id">
    <div class="modal-header"><h5 class="modal-title" id="category-modal-title">Tambah Kategori</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label" for="cat-name">Nama <span class="text-danger">*</span></label><input class="form-control" id="cat-name" name="name" maxlength="100" required></div>
        <div class="mb-3"><label class="form-label" for="cat-desc">Deskripsi</label><input class="form-control" id="cat-desc" name="description" maxlength="255"></div>
        <div class="mb-3"><label class="form-label" for="cat-icon">Ikon <a class="small" href="https://icons.getbootstrap.com/" target="_blank" rel="noopener noreferrer">(daftar ikon)</a></label><input class="form-control" id="cat-icon" name="icon" value="bi-folder" pattern="bi-[a-z0-9-]{1,40}" maxlength="50"></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cat-active" name="is_active" value="1" checked><label class="form-check-label" for="cat-active">Aktif</label></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
</form></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= asset_url('js/pages.js') ?>"></script><?= $this->endSection() ?>
