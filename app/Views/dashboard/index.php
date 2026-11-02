<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= view('partials/page_head', ['title' => 'Dashboard', 'subtitle' => 'Ringkasan repositori berkas.']) ?>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Total Berkas', number_format((int) $totals['n'], 0, ',', '.'), 'bi-files', 'bg-soft-primary'],
        ['Total Ukuran', format_bytes($totals['bytes']), 'bi-hdd', 'bg-soft-info'],
        ['Total Unduhan', number_format((int) $totals['downloads'], 0, ',', '.'), 'bi-download', 'bg-soft-success'],
        $userCount !== null
            ? ['Pengguna', number_format($userCount, 0, ',', '.'), 'bi-people', 'bg-soft-warning']
            : ['Kategori Aktif', number_format($catCount, 0, ',', '.'), 'bi-tags', 'bg-soft-warning'],
    ];
    foreach ($stats as [$label, $value, $icon, $cls]): ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></span>
                <div><div class="stat-value"><?= esc($value) ?></div><div class="stat-label"><?= esc($label) ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-grid me-2"></i>Berkas per Kategori</div>
            <div class="list-group list-group-flush">
                <?php foreach ($perCategory as $c): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" href="<?= site_url('files') ?>">
                        <span><i class="bi <?= esc($c['icon'], 'attr') ?> me-2 text-primary"></i><?= esc($c['name']) ?></span>
                        <span class="badge badge-soft rounded-pill"><?= (int) $c['n'] ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($perCategory === []): ?><div class="empty-state"><i class="bi bi-tags"></i><div>Belum ada kategori.</div></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-clock me-2"></i>Berkas Terbaru</span>
                <a class="small" href="<?= site_url('files') ?>">Lihat semua</a></div>
            <div class="table-responsive-wrap">
                <table class="table table-hover mb-0">
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td style="width:44px" class="text-center"><i class="bi <?= esc(file_icon($r['extension']), 'attr') ?> file-ico"></i></td>
                            <td><a class="title-link" href="<?= site_url('files/' . (int) $r['id']) ?>"><?= esc($r['title']) ?></a>
                                <div class="small text-muted"><?= esc($r['category_name']) ?> · <?= esc($r['uploader_name']) ?></div></td>
                            <td class="text-end small text-muted text-nowrap"><?= esc(format_bytes($r['file_size'])) ?><br><?= esc(date('d/m/Y', strtotime($r['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recent === []): ?><tr><td><div class="empty-state"><i class="bi bi-inbox"></i><div>Belum ada berkas.</div></div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($logs !== []): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-activity me-2"></i>Aktivitas Terakhir</span><a class="small" href="<?= site_url('logs') ?>">Semua log</a></div>
            <div class="table-responsive-wrap"><table class="table table-sm mb-0"><tbody>
                <?php foreach ($logs as $l): ?>
                    <tr><td class="text-nowrap text-muted small"><?= esc($l['created_at']) ?></td><td><?= esc($l['username'] ?? '—') ?></td><td><?= esc($l['description']) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
