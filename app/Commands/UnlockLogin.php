<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Membuka kunci login (hapus catatan percobaan gagal) bila akun terkunci throttle.
 *
 *   php spark repository:unlock admin     buka kunci satu username
 *   php spark repository:unlock --all     buka semua kunci
 *
 * Tanpa akses CLI: phpMyAdmin -> DELETE FROM login_attempts;
 */
class UnlockLogin extends BaseCommand
{
    protected $group       = 'Repository';
    protected $name        = 'repository:unlock';
    protected $description = 'Buka kunci login (throttle) untuk username tertentu atau semua.';
    protected $usage       = 'repository:unlock [username] [--all]';
    protected $arguments   = ['username' => 'Username yang akan dibuka kuncinya.'];
    protected $options     = ['--all' => 'Hapus semua catatan percobaan login gagal.'];

    public function run(array $params)
    {
        $db = db_connect();

        if (array_key_exists('all', $params) || CLI::getOption('all') !== null) {
            $db->table('login_attempts')->truncate();
            CLI::write('Semua kunci login dibuka.', 'green');

            return EXIT_SUCCESS;
        }

        $username = mb_strtolower(trim((string) ($params[0] ?? '')));
        if ($username === '') {
            CLI::error('Sebutkan username, mis.: php spark repository:unlock admin  (atau gunakan --all)');

            return EXIT_ERROR;
        }

        $db->table('login_attempts')->where('identifier', $username)->delete();
        CLI::write("Kunci login untuk \"{$username}\" dibuka ({$db->affectedRows()} catatan dihapus).", 'green');

        return EXIT_SUCCESS;
    }
}
