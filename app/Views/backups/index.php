<?php
/** @var bool $available @var list<array<string,mixed>> $rows @var array<string,mixed> $stats @var bool $canManage @var bool $canRestore @var int $keepAuto */
$typeLabel = ['manual' => ['Manual', 'badge-soft-teal'], 'auto' => ['Otomatis', 'badge-soft-gray'], 'prerestore' => ['Pengaman', 'badge-soft-gray'], 'upload' => ['Diunggah', 'badge-soft-gray']];
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Backup & Pulihkan<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Backup & Pulihkan', 'subtitle' => 'Cadangkan database dan seluruh berkas dalam satu ZIP; pulihkan bila diperlukan.']) ?>

<?php if (! $available): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Ekstensi PHP <code>zip</code> tidak aktif di server ini. Aktifkan lewat panel hosting (PHP Extensions / Select PHP Version).</div>
<?php else: ?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body">
        <div class="text-muted small">Data yang akan dicadangkan</div>
        <div class="fs-5 fw-bold"><?= (int) $stats['tables'] ?> tabel · <?= (int) $stats['uploads_count'] ?> berkas</div>
        <div class="text-muted small"><?= esc(format_bytes($stats['uploads_bytes'])) ?> berkas unggahan<?= $stats['free'] !== null ? ' · ruang disk bebas ' . esc(format_bytes($stats['free'])) : '' ?></div>
    </div></div></div>
    <?php if ($canManage): ?>
    <div class="col-md-8"><div class="card h-100"><div class="card-body">
        <form method="post" action="<?= site_url('backups/create') ?>" class="d-flex flex-wrap gap-3 align-items-center" data-once>
            <?= csrf_field() ?>
            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="include_files" value="1" id="inc-files" checked><label class="form-check-label" for="inc-files">Sertakan seluruh berkas unggahan</label></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-database-down me-1"></i>Buat Backup Sekarang</button>
        </form>
        <div class="small text-muted mt-2">Proses bisa beberapa menit bila berkas banyak; jangan tutup halaman. Backup otomatis terjadwal: lihat bagian bantuan di bawah.</div>
    </div></div></div>
    <?php endif; ?>
</div>

<div class="card mb-3"><div class="card-body">
    <?php if ($rows === []): ?>
        <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada backup.</div>
    <?php else: ?>
    <div class="table-responsive-wrap"><table class="table table-hover align-middle w-100">
        <thead><tr><th>Berkas backup</th><th>Jenis</th><th>Isi</th><th>Ukuran</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $i = $r['info'] ?? []; [$tl, $tc] = $typeLabel[$r['type']] ?? [$r['type'], 'badge-soft-gray']; ?>
            <tr>
                <td class="text-break"><code><?= esc($r['name']) ?></code><?php if (! $r['valid']): ?><div class="text-danger small">Tidak valid / rusak</div><?php endif; ?></td>
                <td><span class="badge <?= esc($tc, 'attr') ?>"><?= esc($tl) ?></span></td>
                <td class="small"><?php if ($r['valid']): ?><?= (int) ($i['rows'] ?? 0) ?> baris · <?= !empty($i['include_files']) ? (int) ($i['file_count'] ?? 0) . ' berkas' : '<em>tanpa berkas</em>' ?><div class="text-muted">v<?= esc((string) ($i['app_version'] ?? '?')) ?><?= !empty($i['created_by']) ? ' · oleh ' . esc((string) $i['created_by']) : '' ?></div><?php else: ?>—<?php endif; ?></td>
                <td><?= esc(format_bytes($r['size'])) ?></td>
                <td class="text-nowrap small"><?= esc(date('d M Y H:i', $r['mtime'])) ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-outline-secondary btn-icon" title="Unduh" href="<?= site_url('backups/download/' . rawurlencode($r['name'])) ?>"><i class="bi bi-download"></i></a>
                    <?php endif; ?>
                    <?php if ($canRestore && $r['valid']): ?>
                        <button class="btn btn-sm btn-outline-warning btn-icon" type="button" title="Pulihkan" data-restore data-name="<?= esc($r['name'], 'attr') ?>" data-action="<?= esc(site_url('backups/restore/' . rawurlencode($r['name'])), 'attr') ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <?php endif; ?>
                    <?php if ($canManage): ?>
                        <button class="btn btn-sm btn-outline-danger btn-icon" type="button" title="Hapus" data-post="<?= site_url('backups/delete/' . rawurlencode($r['name'])) ?>" data-confirm-title="Hapus backup?" data-confirm="Backup ini akan dihapus permanen dari server." data-confirm-button="Ya, hapus"><i class="bi bi-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>

<?php if ($canManage): ?>
<div class="card mb-3"><div class="card-header">Impor backup dari luar</div><div class="card-body">
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= site_url('backups/upload') ?>" enctype="multipart/form-data" data-once>
                <?= csrf_field() ?>
                <label class="form-label" for="backup_file">Unggah berkas backup (.zip)</label>
                <div class="input-group"><input class="form-control" type="file" id="backup_file" name="backup_file" accept=".zip" required><button class="btn btn-outline-primary" type="submit"><i class="bi bi-upload me-1"></i>Unggah</button></div>
                <div class="form-text">Berkas divalidasi penuh dan hanya disimpan; belum memulihkan apa pun. Batas unggah PHP biasanya 20 MB.</div>
            </form>
        </div>
        <div class="col-lg-5">
            <form method="post" action="<?= site_url('backups/scan') ?>" data-once>
                <?= csrf_field() ?>
                <div class="form-label">Backup besar</div>
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-folder-symlink me-1"></i>Pindai folder backup</button>
                <div class="form-text">Unggah ZIP lewat FTP ke <code>writable/backups/</code>, lalu klik tombol ini.</div>
            </form>
        </div>
    </div>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-header">Catatan penting</div><div class="card-body small">
    <ul class="mb-2">
        <li>Backup berisi <strong>seluruh data, hash password, dan semua berkas</strong> (termasuk yang terbatas). Simpan salinannya di tempat aman dan jangan dibagikan.</li>
        <li><strong>Pulihkan menggantikan seluruh database dan berkas</strong> dengan isi backup, termasuk akun pengguna &amp; peran. Anda akan keluar dan harus login dengan akun yang ada di backup. Sebelum memulihkan, sistem otomatis membuat backup pengaman dan mengembalikan data bila pemulihan gagal.</li>
        <li>Lakukan pemulihan saat pengguna lain tidak aktif. Backup dari versi aplikasi lebih lama otomatis disesuaikan skemanya; dari versi lebih baru ditolak.</li>
        <li>Simpan backup juga di luar server (unduh berkala) — backup yang berada di server yang sama tidak melindungi dari kerusakan server.</li>
    </ul>
    <div class="fw-bold">Backup otomatis (cron job)</div>
    Di cPanel &rarr; Cron Jobs, mis. setiap hari 02.00 (simpan <?= (int) $keepAuto ?> terbaru):<br>
    <code>0 2 * * * /usr/local/bin/php <?= esc(ROOTPATH) ?>spark repository:backup --auto</code>
</div></div>
<?php endif; ?>

<?php if ($canRestore): ?>
<div class="modal fade" id="restore-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" id="restore-form" data-once>
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-octagon me-2"></i>Pulihkan dari backup</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body">
        <p class="mb-2">Backup: <code id="restore-name" class="text-break"></code></p>
        <div class="alert alert-warning small">Seluruh <strong>database dan berkas saat ini akan diganti</strong> dengan isi backup ini. Backup pengaman dibuat otomatis lebih dulu. Anda akan keluar dari sesi ini.</div>
        <div class="mb-3"><label class="form-label" for="restore-password">Password Anda <span class="text-danger">*</span></label><input class="form-control" type="password" id="restore-password" name="password" autocomplete="current-password" required></div>
        <div class="mb-0"><label class="form-label" for="restore-confirm">Ketik <code>PULIHKAN</code> untuk melanjutkan <span class="text-danger">*</span></label><input class="form-control" id="restore-confirm" name="confirm_text" autocomplete="off" required pattern="PULIHKAN"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Pulihkan sekarang</button></div>
</form></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= asset_url('js/backups.js') ?>"></script><?= $this->endSection() ?>
