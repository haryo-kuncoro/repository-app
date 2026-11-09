<?php

namespace App\Commands;

use App\Libraries\BackupManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Memulihkan dari backup (MENGGANTI seluruh database & berkas).
 *
 *   php spark repository:restore repo-manual-20261103-101500-ab12cd34.zip --yes
 *   php spark repository:restore /path/ke/backup.zip --yes      (diimpor + divalidasi dulu)
 *   php spark repository:restore --list
 */
class BackupRestore extends BaseCommand
{
    protected $group       = 'Repository';
    protected $name        = 'repository:restore';
    protected $description = 'Pulihkan database + berkas dari backup (menimpa data saat ini).';
    protected $usage       = 'repository:restore <nama-backup|path.zip> --yes | --list';
    protected $arguments   = ['backup' => 'Nama berkas di writable/backups atau path ke ZIP.'];
    protected $options     = ['--yes' => 'Konfirmasi (wajib).', '--list' => 'Tampilkan daftar backup.'];

    public function run(array $params)
    {
        $mgr = new BackupManager();

        if (array_key_exists('list', $params) || CLI::getOption('list') !== null) {
            foreach ($mgr->list() as $b) {
                CLI::write(sprintf('%s  %s  %s', $b['name'], format_bytes($b['size']), date('Y-m-d H:i', $b['mtime'])));
            }

            return EXIT_SUCCESS;
        }

        $arg = (string) ($params[0] ?? '');
        if ($arg === '') {
            CLI::error('Sebutkan nama backup atau path ZIP. Lihat: php spark repository:restore --list');

            return EXIT_ERROR;
        }
        if (! array_key_exists('yes', $params) && CLI::getOption('yes') === null) {
            CLI::error('Pemulihan MENGGANTI seluruh data. Tambahkan --yes untuk melanjutkan.');

            return EXIT_ERROR;
        }

        $name = $arg;
        if (! BackupManager::isValidName($arg)) {
            if (! is_file($arg)) {
                CLI::error('Backup tidak ditemukan.');

                return EXIT_ERROR;
            }
            $imp = $mgr->import($arg);
            if (! $imp['ok']) {
                CLI::error('Backup ditolak: ' . $imp['error']);

                return EXIT_ERROR;
            }
            $name = $imp['name'];
            CLI::write('Backup diimpor sebagai ' . $name);
        }

        $res = $mgr->restore($name, 'cli');
        if (! $res['ok']) {
            CLI::error('Pemulihan gagal: ' . $res['error']);

            return EXIT_ERROR;
        }
        CLI::write(sprintf('Pemulihan selesai: %d berkas, %d baris. Backup pengaman: %s', $res['files'], $res['rows'], $res['prerestore']), 'green');
        foreach ($res['warnings'] ?? [] as $w) {
            CLI::write('Catatan: ' . $w, 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
