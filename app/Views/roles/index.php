<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Peran<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Peran & Hak Akses', 'subtitle' => 'Atur apa yang boleh dilakukan setiap peran (RBAC).', 'actions' => '<a class="btn btn-primary" href="' . site_url('roles/create') . '"><i class="bi bi-plus-lg me-1"></i>Tambah Peran</a>']) ?>
<div class="card"><div class="card-body"><div class="table-responsive-wrap">
<table class="table table-hover datatable w-100">
    <thead><tr><th>Peran</th><th>Deskripsi</th><th>Pengguna</th><th>Hak Akses</th><th class="text-end no-sort">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $super = $r['slug'] === 'superadmin'; ?>
        <tr>
            <td><strong><?= esc($r['name']) ?></strong> <?= (int) $r['is_system'] === 1 ? '<span class="badge badge-soft-gray">sistem</span>' : '' ?></td>
            <td class="text-muted"><?= esc($r['description']) ?></td>
            <td><?= (int) $r['user_count'] ?></td>
            <td><?= $super ? 'Semua' : (int) $r['perm_count'] ?></td>
            <td class="text-end text-nowrap">
                <?php if (! $super): ?><a class="btn btn-sm btn-outline-secondary btn-icon" title="Ubah" href="<?= site_url('roles/' . (int) $r['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (! $super && (int) $r['is_system'] !== 1): ?>
                <button class="btn btn-sm btn-outline-danger btn-icon" type="button" title="Hapus" data-post="<?= site_url('roles/' . (int) $r['id'] . '/delete') ?>" data-confirm-title="Hapus peran?" data-confirm="Peran “<?= esc($r['name'], 'attr') ?>” akan dihapus." data-confirm-button="Ya, hapus"><i class="bi bi-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>
<?= $this->endSection() ?>
