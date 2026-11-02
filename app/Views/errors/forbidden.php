<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak</title>
    <link rel="stylesheet" href="<?= asset_url('vendor/nunito/nunito.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="card p-5 text-center" style="max-width:480px">
        <div class="display-3 text-danger mb-2"><i class="bi bi-shield-exclamation"></i></div>
        <h1 class="h3 fw-bold">403 — Akses Ditolak</h1>
        <p class="text-muted">Anda tidak memiliki hak akses untuk membuka halaman atau menjalankan aksi ini. Hubungi administrator bila Anda merasa ini keliru.</p>
        <div class="d-flex gap-2 justify-content-center">
            <a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            <a class="btn btn-primary" href="<?= esc(service('auth')->homeUrl(), 'attr') ?>"><i class="bi bi-house me-1"></i>Beranda</a>
        </div>
    </div>
</div>
</body>
</html>
