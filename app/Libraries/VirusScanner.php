<?php

namespace App\Libraries;

use Config\Repository;

/**
 * Orkestrator pemindaian setiap unggahan.
 *
 * Lapis 1: HeuristicScanner (murni PHP, selalu tersedia).
 * Lapis 2: ClamAV bila tersedia — lewat daemon clamd (socket Unix / TCP, murni PHP,
 *          cepat & hemat memori) atau biner clamscan (lambat; memuat DB tiap pindai).
 *
 * Mode (Config\Repository::$scanMode): auto | clamav | builtin | off.
 */
class VirusScanner
{
    private const SOCKET_CANDIDATES = [
        '/var/run/clamav/clamd.ctl', '/run/clamav/clamd.ctl', '/var/run/clamd.scan/clamd.sock',
        '/run/clamd.scan/clamd.sock', '/var/run/clamd/clamd.sock', '/tmp/clamd.sock', '/tmp/clamd.socket',
    ];
    private const BINARY_CANDIDATES = ['/usr/bin/clamscan', '/usr/local/bin/clamscan', '/opt/clamav/bin/clamscan', '/usr/sbin/clamscan'];

    private Repository $cfg;
    private ?array $clamInfo = null;

    public function __construct(?Repository $cfg = null)
    {
        $this->cfg = $cfg ?? config(Repository::class);
    }

    /**
     * Pindai satu berkas.
     *
     * @return array{ok: bool, status: string, engine: string, signature: ?string, message: string, engine_down: bool}
     *   status: clean | infected | blocked | unscanned
     *   engine_down: true bila hasil karena mesin tidak dapat dipakai (bukan vonis atas berkas)
     */
    public function scan(string $path, string $ext): array
    {
        $mode = strtolower($this->cfg->scanMode);

        if ($mode === 'off') {
            return $this->result(true, 'unscanned', 'Tanpa pemindaian', null, 'Pemindaian dinonaktifkan.');
        }

        $h = (new HeuristicScanner($this->cfg))->scan($path, $ext);
        if ($h !== null) {
            return $this->result(
                false,
                'blocked',
                'Heuristik',
                $h['signature'],
                'Berkas ditolak oleh pemindai keamanan: ' . $h['detail'] . ' (' . $h['signature'] . ').'
            );
        }

        $engine = 'Heuristik';
        if (in_array($mode, ['auto', 'clamav'], true)) {
            $c = $this->clamScan($path);

            if ($c['state'] === 'infected') {
                return $this->result(
                    false,
                    'infected',
                    'ClamAV (' . $c['method'] . ')',
                    $c['signature'],
                    'Berkas ditolak: terdeteksi virus/malware (' . $c['signature'] . ').'
                );
            }
            if ($c['state'] === 'clean') {
                $engine = 'ClamAV (' . $c['method'] . ') + Heuristik';
            } elseif ($c['state'] === 'error') {
                // Antivirus ada tetapi gagal memeriksa (mis. melebihi batas ukuran): gagal tertutup.
                log_message('error', 'ClamAV gagal memindai: ' . $c['detail']);

                return $this->result(false, 'blocked', 'ClamAV (' . $c['method'] . ')', null, 'Pemindai antivirus gagal memeriksa berkas ini, unggahan ditolak demi keamanan.', true);
            } elseif ($mode === 'clamav') {
                return $this->result(false, 'blocked', 'ClamAV tidak tersedia', null, 'Pemindai antivirus (ClamAV) tidak tersedia, unggahan ditolak demi keamanan. Hubungi administrator.', true);
            }
            // mode auto + ClamAV tidak tersedia: lanjut dengan heuristik saja.
        }

        return $this->result(true, 'clean', $engine, null, 'Bersih.');
    }

    /**
     * Ringkasan mesin yang aktif (untuk ditampilkan ke pengguna/admin).
     *
     * @return array{label: string, clam: bool, mode: string, warning: ?string}
     */
    public function describe(): array
    {
        $mode = strtolower($this->cfg->scanMode);
        if ($mode === 'off') {
            return ['label' => 'Pemindaian virus dinonaktifkan', 'clam' => false, 'mode' => $mode, 'warning' => 'Unggahan tidak dipindai.'];
        }
        if ($mode === 'builtin') {
            return ['label' => 'Pemindai heuristik bawaan', 'clam' => false, 'mode' => $mode, 'warning' => null];
        }

        $info = $this->detectClam();
        if ($info['available']) {
            return ['label' => 'ClamAV (' . $info['method'] . ') + pemindai heuristik', 'clam' => true, 'mode' => $mode, 'warning' => null];
        }
        if ($mode === 'clamav') {
            return ['label' => 'ClamAV tidak tersedia', 'clam' => false, 'mode' => $mode, 'warning' => 'ClamAV wajib tetapi tidak terdeteksi: semua unggahan akan ditolak.'];
        }

        return ['label' => 'Pemindai heuristik bawaan', 'clam' => false, 'mode' => $mode, 'warning' => 'ClamAV tidak terdeteksi di server ini; hanya pemindaian heuristik yang berjalan.'];
    }

    // ------------------------------------------------------------------
    // ClamAV
    // ------------------------------------------------------------------

    /** @return array{available: bool, method: ?string} */
    private function detectClam(): array
    {
        if ($this->clamInfo !== null) {
            return $this->clamInfo;
        }

        foreach ($this->clamdTargets() as [$address, $label]) {
            $fp = $this->connect($address);
            if ($fp === null) {
                continue;
            }
            fwrite($fp, "zPING\0");
            $pong = trim((string) stream_get_contents($fp), "\0 \n");
            fclose($fp);
            if ($pong === 'PONG') {
                return $this->clamInfo = ['available' => true, 'method' => $label];
            }
        }
        if ($this->binary() !== null) {
            return $this->clamInfo = ['available' => true, 'method' => 'clamscan'];
        }

        return $this->clamInfo = ['available' => false, 'method' => null];
    }

    /**
     * @return array{state: string, method: ?string, signature: ?string, detail: string}
     *   state: clean | infected | error | unavailable
     */
    private function clamScan(string $path): array
    {
        foreach ($this->clamdTargets() as [$address, $label]) {
            $fp = $this->connect($address);
            if ($fp !== null) {
                $r = $this->clamdInstream($fp, $path);
                fclose($fp);

                return $r + ['method' => $label];
            }
        }

        $bin = $this->binary();
        if ($bin !== null) {
            return $this->clamscanBinary($bin, $path) + ['method' => 'clamscan'];
        }

        return ['state' => 'unavailable', 'method' => null, 'signature' => null, 'detail' => 'ClamAV tidak ditemukan'];
    }

    /** @return list<array{0: string, 1: string}> */
    private function clamdTargets(): array
    {
        $targets = [];
        if ($this->cfg->clamdSocket !== '') {
            $targets[] = ['unix://' . $this->cfg->clamdSocket, 'clamd-socket'];
        }
        if ($this->cfg->clamdHost !== '') {
            $targets[] = ['tcp://' . $this->cfg->clamdHost . ':' . $this->cfg->clamdPort, 'clamd-tcp'];
        }
        if ($targets === []) {
            foreach (self::SOCKET_CANDIDATES as $socket) {
                if (@file_exists($socket)) {
                    $targets[] = ['unix://' . $socket, 'clamd-socket'];
                }
            }
        }

        return $targets;
    }

    /** @return resource|null */
    private function connect(string $address)
    {
        $errno = 0;
        $errstr = '';
        $fp    = @stream_socket_client($address, $errno, $errstr, 2);
        if ($fp === false) {
            return null;
        }
        stream_set_timeout($fp, max(5, $this->cfg->scanTimeout));

        return $fp;
    }

    /**
     * Protokol clamd INSTREAM: berkas dialirkan ke daemon, tidak perlu izin baca di sisi clamd.
     *
     * @param resource $fp
     *
     * @return array{state: string, signature: ?string, detail: string}
     */
    private function clamdInstream($fp, string $path): array
    {
        $h = @fopen($path, 'rb');
        if ($h === false) {
            return ['state' => 'error', 'signature' => null, 'detail' => 'berkas tidak dapat dibaca'];
        }

        $broken = @fwrite($fp, "zINSTREAM\0") === false;
        while (! $broken && ! feof($h)) {
            $chunk = fread($h, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (@fwrite($fp, pack('N', strlen($chunk)) . $chunk) === false) {
                $broken = true; // clamd menutup koneksi (mis. melebihi StreamMaxLength); baca balasannya
            }
        }
        fclose($h);
        if (! $broken) {
            @fwrite($fp, pack('N', 0));
        }

        $reply = trim((string) stream_get_contents($fp), "\0 \r\n");

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m) === 1) {
            return ['state' => 'infected', 'signature' => $m[1], 'detail' => $reply];
        }
        if ($reply === 'stream: OK') {
            return ['state' => 'clean', 'signature' => null, 'detail' => $reply];
        }

        return ['state' => 'error', 'signature' => null, 'detail' => $reply !== '' ? $reply : 'tidak ada balasan dari clamd'];
    }

    private function binary(): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $disabled, true)) {
            return null;
        }

        $candidates = $this->cfg->clamscanPath !== '' ? [$this->cfg->clamscanPath] : self::BINARY_CANDIDATES;
        foreach ($candidates as $bin) {
            if (@is_executable($bin)) {
                return $bin;
            }
        }

        return null;
    }

    /** @return array{state: string, signature: ?string, detail: string} */
    private function clamscanBinary(string $bin, string $path): array
    {
        $cmd = [$bin, '--no-summary', '--stdout'];
        if ($this->cfg->clamDatabaseDir !== '') {
            $cmd[] = '--database=' . $this->cfg->clamDatabaseDir;
        }
        $cmd[] = '--';
        $cmd[] = $path;

        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            return ['state' => 'error', 'signature' => null, 'detail' => 'clamscan tidak dapat dijalankan'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out      = '';
        $deadline = time() + max(5, $this->cfg->scanTimeout);

        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $status = proc_get_status($proc);
            if (! $status['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $exit = $status['exitcode'];
                break;
            }
            if (time() > $deadline) {
                proc_terminate($proc, 9);
                $exit = -2;
                break;
            }
            usleep(50000);
        }
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($exit === 0) {
            return ['state' => 'clean', 'signature' => null, 'detail' => trim($out)];
        }
        if ($exit === 1 && preg_match('/: (.+) FOUND\s*$/m', $out, $m) === 1) {
            return ['state' => 'infected', 'signature' => $m[1], 'detail' => trim($out)];
        }

        return ['state' => 'error', 'signature' => null, 'detail' => $exit === -2 ? 'waktu pemindaian habis' : trim($out . ' ' . $err)];
    }

    /** @return array{ok: bool, status: string, engine: string, signature: ?string, message: string, engine_down: bool} */
    private function result(bool $ok, string $status, string $engine, ?string $signature, string $message, bool $engineDown = false): array
    {
        return [
            'ok'          => $ok,
            'status'      => $status,
            'engine'      => $engine,
            'signature'   => $signature,
            'message'     => $message,
            'engine_down' => $engineDown,
        ];
    }
}
