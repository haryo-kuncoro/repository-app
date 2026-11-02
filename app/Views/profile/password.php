<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Ganti Password<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Ganti Password', 'subtitle' => 'Gunakan kombinasi huruf besar, kecil, dan angka minimal ' . (int) config('Repository')->passwordMinLength . ' karakter.']) ?>
<?php if ((int) $u['must_change_password'] === 1): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Demi keamanan, Anda wajib mengganti password sebelum menggunakan aplikasi.</div>
<?php endif; ?>
<div class="row"><div class="col-lg-6">
    <div class="card"><div class="card-body">
        <form method="post" action="<?= site_url('profile/password') ?>" data-once autocomplete="off">
            <?= csrf_field() ?>
            <?php foreach ([['current_password', 'Password saat ini', 'current-password'], ['new_password', 'Password baru', 'new-password'], ['confirm_password', 'Ulangi password baru', 'new-password']] as [$n, $l, $ac]): ?>
                <div class="mb-3"><label class="form-label" for="<?= $n ?>"><?= $l ?></label>
                    <input class="form-control<?= has_error($n) ?>" type="password" id="<?= $n ?>" name="<?= $n ?>" maxlength="72" required autocomplete="<?= $ac ?>"><?= field_error($n) ?></div>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Simpan Password</button>
        </form>
    </div></div>
</div></div>
<?= $this->endSection() ?>
