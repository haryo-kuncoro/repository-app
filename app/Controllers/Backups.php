<?php

namespace App\Controllers;

use App\Libraries\BackupManager;
use App\Libraries\Throttle;
use App\Models\UserModel;

/**
 * Backup & pemulihan (database + seluruh berkas).
 * backup.manage  : buat / unduh / unggah / hapus backup
 * backup.restore : memulihkan (mengganti SEMUA data) — butuh password ulang + ketik PULIHKAN
 */
class Backups extends BaseController
{
    private BackupManager $backups;

    public function __construct()
    {
        $this->backups = new BackupManager();
    }

    public function index()
    {
        return view('backups/index', [
            'available' => BackupManager::available(),
            'rows'      => BackupManager::available() ? $this->backups->list() : [],
            'stats'     => $this->backups->stats(),
            'canManage' => can('backup.manage'),
            'canRestore' => can('backup.restore'),
            'keepAuto'  => config('Repository')->backupKeepAuto,
        ]);
    }

    public function create()
    {
        $include = (string) $this->request->getPost('include_files') === '1';
        $res     = $this->backups->create('manual', $include, (string) ($this->me()['username'] ?? ''));

        if (! $res['ok']) {
            log_activity('backup_failed', 'Backup gagal: ' . $res['error']);

            return $this->flash('backups', 'error', 'Backup gagal: ' . $res['error']);
        }
        log_activity('backup_create', 'Membuat backup ' . $res['name'] . ($include ? '' : ' (hanya database)'));

        return $this->flash('backups', 'success', sprintf('Backup dibuat: %d baris data, %d berkas, %s.', $res['rows'], $res['files'], format_bytes($res['size'])));
    }

    public function upload()
    {
        $file = $this->request->getFile('backup_file');
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return $this->flash('backups', 'error', 'Pilih berkas backup (.zip) yang valid. Bila berkas besar, unggah lewat FTP ke writable/backups lalu klik "Pindai folder".');
        }
        if (strtolower($file->getClientExtension()) !== 'zip') {
            return $this->flash('backups', 'error', 'Hanya berkas .zip yang diterima.');
        }

        $res = $this->backups->import($file->getTempName());
        if (! $res['ok']) {
            log_activity('backup_upload_rejected', 'Unggahan backup ditolak: ' . $res['error']);

            return $this->flash('backups', 'error', 'Backup ditolak: ' . $res['error']);
        }
        log_activity('backup_upload', 'Mengunggah backup ' . $res['name']);

        return $this->flash('backups', 'success', 'Backup diunggah dan lolos validasi. Klik "Pulihkan" bila ingin memakainya.');
    }

    public function scan()
    {
        $res = $this->backups->scanFolder();
        log_activity('backup_scan', 'Pindai folder backup: ' . $res['imported'] . ' diimpor, ' . count($res['rejected']) . ' ditolak');
        if ($res['rejected'] !== []) {
            return $this->flash('backups', 'warning', $res['imported'] . ' backup diimpor. Ditolak: ' . implode(' | ', array_slice($res['rejected'], 0, 5)));
        }

        return $this->flash('backups', $res['imported'] > 0 ? 'success' : 'info', $res['imported'] > 0 ? $res['imported'] . ' backup diimpor dari folder.' : 'Tidak ada berkas ZIP baru di folder backup.');
    }

    public function download(string $name)
    {
        $path = $this->backups->path($name);
        if ($path === null) {
            $this->notFound();
        }
        log_activity('backup_download', 'Mengunduh backup ' . $name);

        $resp = $this->response->download($path, null)->setFileName($name);
        $resp->setHeader('Cache-Control', 'no-store');

        return $resp;
    }

    public function delete(string $name)
    {
        if ($this->backups->path($name) === null) {
            $this->notFound();
        }
        $this->backups->delete($name);
        log_activity('backup_delete', 'Menghapus backup ' . $name);

        return $this->flash('backups', 'success', 'Backup dihapus.');
    }

    public function restore(string $name)
    {
        if ($this->backups->path($name) === null) {
            $this->notFound();
        }
        $me       = $this->me();
        $throttle = new Throttle();
        $ident    = 'restore:' . (int) $me['id'];

        if (($wait = $throttle->retryAfter($ident, client_ip())) > 0) {
            return $this->flash('backups', 'error', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($wait / 60) . ' menit.');
        }

        $row = (new UserModel())->find((int) $me['id']);
        $ok  = $row !== null
            && password_verify((string) $this->request->getPost('password'), (string) $row['password_hash'])
            && trim((string) $this->request->getPost('confirm_text')) === 'PULIHKAN';

        if (! $ok) {
            $throttle->hit($ident, client_ip());
            log_activity('restore_denied', 'Pemulihan ditolak (password / konfirmasi salah) untuk ' . $name);

            return $this->flash('backups', 'error', 'Password salah atau teks konfirmasi bukan "PULIHKAN".');
        }
        $throttle->clear($ident, client_ip());

        $res = $this->backups->restore($name, (string) ($me['username'] ?? ''));
        if (! $res['ok']) {
            log_activity('restore_failed', 'Pemulihan ' . $name . ' gagal: ' . $res['error']);

            return $this->flash('backups', 'error', 'Pemulihan gagal: ' . $res['error']);
        }

        // Tabel pengguna sudah diganti: akhiri sesi lama, minta login ulang.
        $session = session();
        $session->remove(['auth_uid', 'auth_ua', 'auth_time']);
        $session->regenerate(true);
        log_activity('restore_done', 'Pemulihan dari ' . $name . ' selesai (backup pengaman: ' . $res['prerestore'] . ')', ['id' => null, 'username' => $me['username'] ?? null]);

        $msg = 'Pemulihan selesai: ' . $res['files'] . ' berkas dan ' . $res['rows'] . ' baris data dipulihkan. Silakan login dengan akun yang ada di backup.';
        if (! empty($res['warnings'])) {
            $msg .= ' Catatan: ' . implode(' ', $res['warnings']);
        }

        return redirect()->to(site_url('login'))->with('success', $msg);
    }
}
