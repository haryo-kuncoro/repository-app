<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Daftar Berkas<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', [
    'title' => 'Daftar Berkas', 'subtitle' => 'Cari, saring, dan kelola berkas repositori.',
    'actions' => can('files.create') ? '<a class="btn btn-primary" href="' . site_url('files/create') . '"><i class="bi bi-cloud-arrow-up me-1"></i>Unggah Berkas</a>' : '',
]) ?>
<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4"><select class="form-select" id="f-category"><option value="">Semua kategori</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><select class="form-select" id="f-year"><option value="">Semua tahun</option>
                <?php foreach ($years as $y): ?><option value="<?= (int) $y ?>"><?= (int) $y ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><select class="form-select" id="f-visibility"><option value="">Semua visibilitas</option><option value="umum">Umum</option><option value="terbatas">Terbatas</option></select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" id="f-reset" type="button"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button></div>
        </div>
        <div class="table-responsive-wrap">
            <table class="table table-hover align-middle w-100" id="files-table" data-url="<?= site_url('files/data') ?>">
                <thead><tr><th></th><th>Judul</th><th>Kategori</th><th>Penulis</th><th>Tahun</th><th>Ukuran</th><th>Diunggah</th><th class="text-end">Aksi</th></tr></thead>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= asset_url('js/files.js') ?>"></script><?= $this->endSection() ?>
