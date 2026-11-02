<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Pengguna<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Pengguna', 'subtitle' => 'Kelola akun dan peran pengguna.', 'actions' => '<a class="btn btn-primary" href="' . site_url('users/create') . '"><i class="bi bi-person-plus me-1"></i>Tambah Pengguna</a>']) ?>
<div class="card"><div class="card-body"><div class="table-responsive-wrap">
<table class="table table-hover datatable w-100">
    <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Peran</th><th>Berkas</th><th>Login Terakhir</th><th>Status</th><th class="text-end no-sort">Aksi</th></tr></thead>
    <tbody>
    <?php $meId = (int) service('auth')->id(); foreach ($rows as $r): if ($r['role_slug'] === 'superadmin' && ! service('auth')->isSuperadmin()) { continue; } ?>
        <tr>
            <td><strong><?= esc($r['name']) ?></strong></td>
            <td><?= esc($r['username']) ?></td>
            <td class="text-muted"><?= esc($r['email']) ?></td>
            <td><span class="badge badge-soft"><?= esc($r['role_name']) ?></span></td>
            <td><?= (int) $r['file_count'] ?></td>
            <td class="small text-muted"><?= esc($r['last_login_at'] ?? '—') ?></td>
            <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-soft-teal">Aktif</span>' : '<span class="badge badge-soft-gray">Nonaktif</span>' ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary btn-icon" title="Ubah" href="<?= site_url('users/' . (int) $r['id'] . '/edit') ?>"><i class="bi bi-pencil"></i></a>
                <?php if ((int) $r['id'] !== $meId): ?>
                <button class="btn btn-sm btn-outline-danger btn-icon" type="button" title="Hapus" data-post="<?= site_url('users/' . (int) $r['id'] . '/delete') ?>" data-confirm-title="Hapus pengguna?" data-confirm="Akun “<?= esc($r['username'], 'attr') ?>” akan dihapus permanen." data-confirm-button="Ya, hapus"><i class="bi bi-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>
<?= $this->endSection() ?>
