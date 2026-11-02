<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Log Aktivitas<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Log Aktivitas', 'subtitle' => 'Jejak audit login, unggah, unduh, dan perubahan data.']) ?>
<div class="card"><div class="card-body">
    <div class="row g-2 mb-3"><div class="col-md-4"><select class="form-select" id="l-action"><option value="">Semua aksi</option>
        <?php foreach ($actionList as $a): ?><option value="<?= esc($a, 'attr') ?>"><?= esc($a) ?></option><?php endforeach; ?></select></div></div>
    <div class="table-responsive-wrap"><table class="table table-sm table-hover w-100" id="logs-table" data-url="<?= site_url('logs/data') ?>">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead></table></div>
</div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= asset_url('js/logs.js') ?>"></script><?= $this->endSection() ?>
