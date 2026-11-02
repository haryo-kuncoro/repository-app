<?php
$cfg = config('Repository');
$old = (string) session()->getFlashdata('old_username');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-name" content="<?= esc(csrf_token(), 'attr') ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName, 'attr') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <title>Masuk · <?= esc($cfg->appName) ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/nunito/nunito.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('vendor/sweetalert2/sweetalert2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="row g-0">
            <div class="col-lg-5 d-none d-lg-block">
                <div class="auth-side">
                    <span class="brand-mark mb-4"><i class="bi bi-archive"></i></span>
                    <h2 class="fw-bold mb-1"><?= esc($cfg->appName) ?></h2>
                    <p class="opacity-75 mb-4"><?= esc($cfg->institution) ?></p>
                    <ul class="list-unstyled small mb-0">
                        <li><i class="bi bi-check2-circle"></i> Simpan skripsi, berkas akreditasi, monev, dan dokumen penting</li>
                        <li><i class="bi bi-check2-circle"></i> Akses diatur per peran (RBAC)</li>
                        <li><i class="bi bi-check2-circle"></i> Berkas tersimpan aman, hanya dapat diunduh setelah login</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="auth-form">
                    <h1 class="h4 fw-bold mb-1">Masuk ke akun Anda</h1>
                    <p class="text-muted mb-4">Gunakan username atau email yang terdaftar.</p>

                    <form id="login-form" method="post" action="<?= site_url('login') ?>" autocomplete="off" data-captcha-url="<?= site_url('captcha/verify') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="username">Username / Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input class="form-control" type="text" id="username" name="username" maxlength="150" required autofocus autocomplete="username" value="<?= esc($old, 'attr') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input class="form-control" type="password" id="password" name="password" maxlength="255" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-pw" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>

                        <!-- Honeypot: disembunyikan dari pengguna, diisi oleh bot -->
                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <?php if ($captcha['driver'] === 'builtin'): ?>
                                <div class="captcha-box" id="captcha-box" data-driver="builtin" data-nonce="<?= esc($captcha['nonce'], 'attr') ?>">
                                    <label for="captcha-check"><input type="checkbox" id="captcha-check"> Saya bukan robot</label>
                                    <span class="captcha-status" id="captcha-status"><i class="bi bi-shield-check"></i> Verifikasi</span>
                                </div>
                                <input type="hidden" name="captcha_token" id="captcha_token" value="">
                            <?php elseif ($captcha['driver'] === 'recaptcha'): ?>
                                <div class="g-recaptcha" data-sitekey="<?= esc($captcha['siteKey'], 'attr') ?>"></div>
                            <?php else: ?>
                                <div class="cf-turnstile" data-sitekey="<?= esc($captcha['siteKey'], 'attr') ?>"></div>
                            <?php endif; ?>
                        </div>

                        <button class="btn btn-primary w-100 py-2" type="submit" id="login-submit" <?= $captcha['driver'] === 'builtin' ? 'disabled' : '' ?>>
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                        </button>
                    </form>
                    <p class="text-muted small mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>Setelah <?= (int) $cfg->maxAttemptsPerAccount ?> kali gagal, login dikunci sementara selama <?= (int) $cfg->lockMinutes ?> menit.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?= view('partials/flash') ?>
<script src="<?= asset_url('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset_url('vendor/sweetalert2/sweetalert2.min.js') ?>"></script>
<script src="<?= asset_url('js/app.js') ?>"></script>
<script src="<?= asset_url('js/login.js') ?>"></script>
<?php if ($captcha['driver'] === 'recaptcha'): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php elseif ($captcha['driver'] === 'turnstile'): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
