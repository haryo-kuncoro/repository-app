<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?><?= esc($f['title']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$actions = '<a class="btn btn-outline-secondary" href="' . site_url('files') . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
if (can('files.download')) { $actions .= '<a class="btn btn-primary" href="' . site_url('files/' . (int) $f['id'] . '/download') . '"><i class="bi bi-download me-1"></i>Unduh</a>'; }
if ($canPreview) { $actions .= '<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="' . site_url('files/' . (int) $f['id'] . '/preview') . '"><i class="bi bi-eye me-1"></i>Pratinjau</a>'; }
if ($canEdit) { $actions .= '<a class="btn btn-outline-secondary" href="' . site_url('files/' . (int) $f['id'] . '/edit') . '"><i class="bi bi-pencil me-1"></i>Ubah</a>'; }
?>
<?= view('partials/page_head', ['title' => $f['title'], 'subtitle' => $f['category_name'], 'actions' => $actions]) ?>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
            <i class="bi <?= esc(file_icon($f['extension']), 'attr') ?>" style="font-size:3rem"></i>
            <div><div class="fw-bold"><?= esc($f['original_name']) ?></div><div class="text-muted small"><?= esc(strtoupper($f['extension'])) ?> · <?= esc(format_bytes($f['file_size'])) ?></div></div>
        </div>
        <dl class="meta-grid row mb-0">
            <div class="col-md-6"><dt>Penulis / Pemilik</dt><dd><?= esc($f['author'] ?: '—') ?></dd></div>
            <div class="col-md-6"><dt>Tahun</dt><dd><?= esc($f['doc_year'] ?: '—') ?></dd></div>
            <div class="col-12"><dt>Kata Kunci</dt><dd><?php foreach (array_filter(array_map('trim', explode(',', (string) $f['keywords']))) as $k): ?><span class="badge badge-soft-gray me-1"><?= esc($k) ?></span><?php endforeach; ?><?= $f['keywords'] ? '' : '—' ?></dd></div>
            <div class="col-12"><dt>Deskripsi</dt><dd><?= $f['description'] ? nl2br(esc($f['description'])) : '—' ?></dd></div>
        </dl>
    </div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Informasi</div><div class="card-body"><dl class="meta-grid mb-0">
            <dt>Visibilitas</dt><dd><?= $f['visibility'] === 'umum' ? '<span class="badge badge-soft-teal">Umum</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-lock-fill"></i> Terbatas</span>' ?></dd>
            <dt>Diunggah oleh</dt><dd><?= esc($f['uploader_name']) ?></dd>
            <dt>Tanggal unggah</dt><dd><?= esc($f['created_at']) ?></dd>
            <dt>Diunduh</dt><dd><?= (int) $f['download_count'] ?> kali</dd>
            <dt>Pemindaian virus</dt><dd><?php
                $ss = $f['scan_status'] ?? 'unscanned';
                if ($ss === 'clean') { echo '<span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-shield-check"></i> Bersih</span>'; }
                elseif ($ss === 'infected') { echo '<span class="badge bg-danger"><i class="bi bi-bug"></i> Terinfeksi</span>'; }
                elseif ($ss === 'blocked') { echo '<span class="badge bg-danger"><i class="bi bi-shield-exclamation"></i> Diblokir</span>'; }
                else { echo '<span class="badge badge-soft-gray">Belum dipindai</span>'; }
            ?><?php if (! empty($f['scan_engine'])): ?><div class="small text-muted mt-1"><?= esc($f['scan_engine']) ?> · <?= esc($f['scanned_at']) ?></div><?php endif; ?></dd>
            <dt>Checksum SHA-256</dt><dd class="small font-monospace"><?= esc($f['checksum']) ?></dd>
        </dl></div></div>
        <?php if (can('files.manage_all')): ?>
        <form class="mb-2" method="post" action="<?= site_url('files/' . (int) $f['id'] . '/rescan') ?>" data-once>
            <?= csrf_field() ?><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-shield-check me-1"></i>Pindai Ulang</button>
        </form>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <form method="post" action="<?= site_url('files/' . (int) $f['id'] . '/delete') ?>" data-confirm="“<?= esc($f['title'], 'attr') ?>” akan dipindahkan ke tempat sampah." data-confirm-title="Hapus berkas?" data-confirm-button="Ya, hapus">
            <?= csrf_field() ?><button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash me-1"></i>Pindahkan ke Tempat Sampah</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
