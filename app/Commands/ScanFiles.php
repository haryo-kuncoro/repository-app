<?php

namespace App\Commands;

use App\Libraries\Uploader;
use App\Libraries\VirusScanner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Pindai ulang berkas tersimpan (cocok untuk cron job mingguan agar signature terbaru terpakai).
 *
 *   php spark repository:scan          hanya berkas yang belum pernah dipindai
 *   php spark repository:scan --all    semua berkas
 *
 * Contoh cron cPanel (tiap Minggu 02:00):
 *   0 2 * * 0  /usr/local/bin/php /home/USER/repository/spark repository:scan --all
 */
class ScanFiles extends BaseCommand
{
    protected $group       = 'Repository';
    protected $name        = 'repository:scan';
    protected $description = 'Pindai berkas tersimpan dengan pemindai virus dan perbarui statusnya.';
    protected $usage       = 'repository:scan [--all]';
    protected $options     = ['--all' => 'Pindai semua berkas, bukan hanya yang belum dipindai.'];

    public function run(array $params)
    {
        $all      = array_key_exists('all', $params) || CLI::getOption('all') !== null;
        $db       = db_connect();
        $uploader = new Uploader();
        $scanner  = new VirusScanner();

        CLI::write('Pemindai: ' . $scanner->describe()['label'], 'yellow');

        $b = $db->table('files')->select('id, title, extension, stored_name')->where('deleted_at', null);
        if (! $all) {
            $b->where('scan_status', 'unscanned');
        }

        $clean = $bad = $skip = 0;
        foreach ($b->get()->getResultArray() as $f) {
            $path = $uploader->path($f['stored_name']);
            if ($path === null) {
                CLI::write("#{$f['id']} berkas fisik hilang: {$f['title']}", 'red');
                $skip++;
                continue;
            }
            $r = $scanner->scan($path, $f['extension']);
            if ($r['engine_down']) {
                CLI::write('Pemindai tidak tersedia: ' . $r['message'], 'red');

                return EXIT_ERROR;
            }
            $db->table('files')->where('id', $f['id'])->update([
                'scan_status' => $r['status'],
                'scan_engine' => mb_substr($r['engine'], 0, 80),
                'scanned_at'  => date('Y-m-d H:i:s'),
            ]);
            if ($r['ok']) {
                $clean++;
            } else {
                $bad++;
                CLI::write("#{$f['id']} DIBLOKIR [{$r['signature']}]: {$f['title']}", 'red');
            }
        }

        CLI::write("Selesai. Bersih: {$clean}, bermasalah: {$bad}, dilewati: {$skip}.", $bad > 0 ? 'red' : 'green');

        return $bad > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
