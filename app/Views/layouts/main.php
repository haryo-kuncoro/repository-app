<?php
/** @var \CodeIgniter\View\View $this */
$me      = current_user();
$cfg     = config('Repository');
$current = trim(uri_string(), '/');
$active  = static fn (string $prefix): string => ($current === $prefix || str_starts_with($current, $prefix . '/')) ? ' active' : '';

$nav = [
    ['dashboard', 'Dashboard', 'bi-speedometer2', 'dashboard.view'],
    ['files', 'Daftar Berkas', 'bi-folder2-open', 'files.view'],
    ['files/create', 'Unggah Berkas', 'bi-cloud-arrow-up', 'files.create'],
    ['files/trash', 'Tempat Sampah', 'bi-trash3', 'files.manage_all'],
];
$admin = [
    ['categories', 'Kategori', 'bi-tags', 'categories.manage'],
    ['users', 'Pengguna', 'bi-people', 'users.manage'],
    ['roles', 'Peran & Hak Akses', 'bi-shield-lock', 'roles.manage'],
    ['logs', 'Log Aktivitas', 'bi-clock-history', 'logs.view'],
    ['backups', 'Backup & Pulihkan', 'bi-database-down', 'backup.manage,backup.restore'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="base-url" content="<?= esc(rtrim(base_url(), '/'), 'attr') ?>">
    <meta name="csrf-name" content="<?= esc(csrf_token(), 'attr') ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName, 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <title><?= esc($this->renderSection('title') ?: 'Halaman') ?> · <?= esc($cfg->appName) ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/nunito/nunito.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/datatables/dataTables.bootstrap5.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/sweetalert2/sweetalert2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= esc($me ? service('auth')->homeUrl() : site_url('login'), 'attr') ?>">
            <span class="brand-mark"><i class="bi bi-archive"></i></span>
            <span><span class="brand-name d-block"><?= esc($cfg->appName) ?></span><span class="brand-sub">Repositori Berkas</span></span>
        </a>
        <nav class="nav flex-column">
            <div class="nav-caption">Menu</div>
            <?php foreach ($nav as [$uri, $label, $icon, $perm]): if (! can(...explode(",", $perm))) { continue; } ?>
                <?php $isActive = $uri === 'files' ? ($current === 'files' || (str_starts_with($current, 'files/') && ! str_starts_with($current, 'files/create') && ! str_starts_with($current, 'files/trash'))) : ($current === $uri); ?>
                <a class="nav-link<?= $isActive ? ' active' : '' ?>" href="<?= site_url($uri) ?>"><i class="bi <?= esc($icon, 'attr') ?>"></i><?= esc($label) ?></a>
            <?php endforeach; ?>

            <?php $adminItems = array_filter($admin, static fn ($i) => can(...explode(",", $i[3]))); ?>
            <?php if ($adminItems !== []): ?>
                <div class="nav-caption">Administrasi</div>
                <?php foreach ($adminItems as [$uri, $label, $icon, $perm]): ?>
                    <a class="nav-link<?= $active($uri) ?>" href="<?= site_url($uri) ?>"><i class="bi <?= esc($icon, 'attr') ?>"></i><?= esc($label) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="nav-caption">Akun</div>
            <a class="nav-link<?= $active('profile') ?>" href="<?= site_url('profile') ?>"><i class="bi bi-person-circle"></i>Profil Saya</a>
        </nav>
        <div class="sidebar-foot"><?= esc($cfg->institution) ?></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-outline-secondary d-lg-none" id="sidebar-toggle" type="button" aria-label="Menu"><i class="bi bi-list"></i></button>
            <div class="me-auto text-muted small d-none d-md-block"><i class="bi bi-calendar3 me-1"></i><?= esc(date('d/m/Y')) ?></div>
            <div class="dropdown">
                <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                    <span class="text-end d-none d-sm-block lh-sm">
                        <span class="d-block fw-bold small"><?= esc($me['name'] ?? '') ?></span>
                        <span class="d-block text-muted" style="font-size:.72rem"><?= esc($me['role_name'] ?? '') ?></span>
                    </span>
                    <span class="avatar"><?= esc(initials((string) ($me['name'] ?? ''))) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="<?= site_url('profile') ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('profile/password') ?>"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="<?= site_url('logout') ?>" method="post" data-confirm="Anda akan keluar dari aplikasi." data-confirm-title="Keluar?" data-confirm-icon="question" data-confirm-button="Ya, keluar">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            <?= $this->renderSection('content') ?>
        </main>
        <footer class="footer">&copy; <?= date('Y') ?> <?= esc($cfg->appName) ?> — <?= esc($cfg->institution) ?></footer>
    </div>
</div>

<?= view('partials/flash') ?>

<script src="<?= asset_url('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_url('vendor/datatables/dataTables.min.js') ?>"></script>
<script src="<?= asset_url('vendor/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= asset_url('vendor/sweetalert2/sweetalert2.min.js') ?>"></script>
<script src="<?= asset_url('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
