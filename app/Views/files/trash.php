<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Tempat Sampah<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Tempat Sampah', 'subtitle' => 'Pulihkan berkas atau hapus permanen (tidak dapat dibatalkan).']) ?>
<div class="card"><div class="card-body"><div class="table-responsive-wrap">
<table class="table table-hover datatable w-100">
    <thead><tr><th>Judul</th><th>Kategori</th><th>Ukuran</th><th>Dihapus</th><th class="text-end no-sort">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><i class="bi <?= esc(file_icon($r['extension']), 'attr') ?> me-2"></i><?= esc($r['title']) ?><div class="small text-muted"><?= esc($r['uploader_name']) ?></div></td>
            <td><?= esc($r['category_name']) ?></td>
            <td data-order="<?= (int) $r['file_size'] ?>"><?= esc(format_bytes($r['file_size'])) ?></td>
            <td><?= esc($r['deleted_at']) ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-success" data-post="<?= site_url('files/' . (int) $r['id'] . '/restore') ?>" data-confirm-icon="question" data-confirm-title="Pulihkan berkas?" data-confirm="Berkas akan muncul kembali di daftar." data-confirm-button="Ya, pulihkan"><i class="bi bi-arrow-counterclockwise"></i> Pulihkan</button>
                <button class="btn btn-sm btn-outline-danger" data-post="<?= site_url('files/' . (int) $r['id'] . '/purge') ?>" data-confirm-title="Hapus permanen?" data-confirm="Berkas fisik akan dihapus dan TIDAK dapat dikembalikan." data-confirm-button="Ya, hapus permanen"><i class="bi bi-x-octagon"></i> Hapus Permanen</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>
<?= $this->endSection() ?>
