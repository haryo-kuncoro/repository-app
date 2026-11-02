<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Profil<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Profil Saya', 'subtitle' => 'Kelola data akun Anda.']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Data Akun</div><div class="card-body">
            <form method="post" action="<?= site_url('profile') ?>" data-once>
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" value="<?= esc($u['username'], 'attr') ?>" disabled></div>
                <div class="mb-3"><label class="form-label" for="name">Nama</label>
                    <input class="form-control<?= has_error('name') ?>" id="name" name="name" maxlength="100" required value="<?= esc(old_value('name', $u['name']), 'attr') ?>"><?= field_error('name') ?></div>
                <div class="mb-3"><label class="form-label" for="email">Email</label>
                    <input class="form-control<?= has_error('email') ?>" id="email" name="email" type="email" maxlength="150" required value="<?= esc(old_value('email', $u['email']), 'attr') ?>"><?= field_error('email') ?></div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header">Keamanan</div><div class="card-body">
            <dl class="meta-grid mb-3">
                <dt>Peran</dt><dd><span class="badge badge-soft"><?= esc($u['role_name']) ?></span></dd>
                <dt>Login terakhir</dt><dd><?= esc($u['last_login_at'] ?? '—') ?> <span class="text-muted small"><?= esc($u['last_login_ip'] ?? '') ?></span></dd>
            </dl>
            <a class="btn btn-outline-primary" href="<?= site_url('profile/password') ?>"><i class="bi bi-key me-1"></i>Ganti Password</a>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
