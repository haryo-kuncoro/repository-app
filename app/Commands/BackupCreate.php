<?php

namespace App\Commands;

use App\Libraries\BackupManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Membuat backup (database + berkas). Cocok untuk cron job.
 *
 *   php spark repository:backup               backup manual lengkap
 *   php spark repository:backup --auto        jenis "otomatis" (yang lama dipangkas)
 *   php spark repository:backup --auto --keep 14
 *   php spark repository:backup --db-only     tanpa berkas unggahan
 */
class BackupCreate extends BaseCommand
{
    protected $group       = 'Repository';
    protected $name        = 'repository:backup';
    protected $description = 'Buat backup database + berkas unggahan ke writable/backups.';
    protected $usage       = 'repository:backup [--auto] [--keep=N] [--db-only]';
    protected $options     = [
        '--auto'    => 'Tandai sebagai backup otomatis; sisakan N terbaru (default dari konfigurasi).',
        '--keep'    => 'Jumlah backup otomatis yang disimpan.',
        '--db-only' => 'Hanya database.',
    ];

    public function run(array $params)
    {
        $mgr  = new BackupManager();
        $auto = array_key_exists('auto', $params) || CLI::getOption('auto') !== null;
        $keep = $params['keep'] ?? CLI::getOption('keep');
        foreach (array_keys(CLI::getOptions()) as $opt) { // dukung juga --keep=14
            if (is_string($opt) && str_starts_with($opt, 'keep=')) {
                $keep = substr($opt, 5);
            }
        }
        $db   = array_key_exists('db-only', $params) || CLI::getOption('db-only') !== null;

        $res = $mgr->create($auto ? 'auto' : 'manual', ! $db, 'cli');
        if (! $res['ok']) {
            CLI::error('Backup gagal: ' . $res['error']);

            return EXIT_ERROR;
        }
        if ($auto && is_numeric($keep) && (int) $keep > 0) {
            $mgr->prune((int) $keep);
        }
        CLI::write(sprintf('Backup dibuat: %s (%d baris, %d berkas, %s)', $res['name'], $res['rows'], $res['files'], format_bytes($res['size'])), 'green');

        return EXIT_SUCCESS;
    }
}
