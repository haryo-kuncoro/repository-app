<?php

namespace App\Libraries;

use Config\Repository;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Backup & pemulihan: database + seluruh berkas unggahan dalam SATU berkas ZIP.
 *
 * Isi ZIP:
 *   info.json       ringkasan kecil (untuk daftar)
 *   manifest.json   daftar berkas + SHA-256 (untuk verifikasi saat pemulihan)
 *   database.sql    dump yang dibuat aplikasi sendiri, satu pernyataan per baris
 *   files/YYYY/MM/<sha1>.upl   berkas unggahan
 *
 * Prinsip keamanan:
 *  - Tersimpan di writable/backups (di luar webroot, .htaccess deny-all); nama acak.
 *  - Nama backup divalidasi regex ketat + realpath; tidak ada path dari pengguna yang dipakai langsung.
 *  - Saat pemulihan ZIP TIDAK diekstrak dengan extractTo(); setiap entri divalidasi (nama, ukuran,
 *    symlink, SHA-256) dan ditulis lewat stream ke folder staging.
 *  - SQL tidak dijalankan mentah: setiap baris harus cocok dengan daftar putih (DROP/CREATE/INSERT
 *    hanya untuk tabel aplikasi) dan divalidasi SELURUHNYA sebelum satu pun dieksekusi.
 *  - Sebelum memulihkan selalu dibuat backup "prerestore"; bila gagal, database dikembalikan otomatis.
 */
class BackupManager
{
    public const FORMAT = 1;

    /** Nama berkas backup yang sah. */
    private const NAME_RE = '/^repo-(manual|auto|prerestore|upload)-\d{8}-\d{6}-[a-f0-9]{8}\.zip$/';
    /** Berkas unggahan yang sah di dalam backup (relatif terhadap folder uploads/repository). */
    private const STORED_RE = '#^\d{4}/\d{2}/[a-f0-9]{40}\.upl$#';

    private Repository $cfg;
    /** @var resource|null */
    private $lock = null;

    public function __construct(?Repository $cfg = null)
    {
        $this->cfg = $cfg ?? config(Repository::class);
    }

    // ------------------------------------------------------------------
    // Lokasi & daftar
    // ------------------------------------------------------------------

    public static function dir(): string
    {
        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups';
    }

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_RE, $name) === 1;
    }

    /** Path penuh backup bila nama sah & berkasnya ada, selain itu null. */
    public function path(string $name): ?string
    {
        if (! self::isValidName($name)) {
            return null;
        }
        $base = realpath(self::dir());
        $full = realpath(self::dir() . DIRECTORY_SEPARATOR . $name);
        if ($base === false || $full === false || dirname($full) !== $base || ! is_file($full)) {
            return null;
        }

        return $full;
    }

    /** @return list<array<string,mixed>> terbaru lebih dulu */
    public function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . 'repo-*.zip') ?: [] as $file) {
            $name = basename($file);
            if (! self::isValidName($name)) {
                continue;
            }
            $info = $this->readSmallEntry($file, 'info.json');
            $out[] = [
                'name'   => $name,
                'type'   => explode('-', $name)[1],
                'size'   => (int) filesize($file),
                'mtime'  => (int) filemtime($file),
                'info'   => $info,
                'valid'  => $info !== null && ($info['format'] ?? null) === self::FORMAT,
            ];
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime'] ?: strcmp($b['name'], $a['name']));

        return $out;
    }

    /** @return array{uploads_bytes:int, uploads_count:int, free:int|null, tables:int} */
    public function stats(): array
    {
        $bytes = 0;
        $count = 0;
        foreach ($this->uploadFiles() as [, $size]) {
            $bytes += $size;
            $count++;
        }
        $free = @disk_free_space(self::dir());

        return [
            'uploads_bytes' => $bytes,
            'uploads_count' => $count,
            'free'          => $free === false ? null : (int) $free,
            'tables'        => count($this->tables()),
        ];
    }

    // ------------------------------------------------------------------
    // Membuat backup
    // ------------------------------------------------------------------

    /**
     * @return array{ok:bool, name?:string, error?:string, size?:int, files?:int, rows?:int}
     */
    public function create(string $type = 'manual', bool $includeFiles = true, ?string $by = null): array
    {
        if (! self::available()) {
            return ['ok' => false, 'error' => 'Ekstensi PHP "zip" (ZipArchive) tidak aktif di server ini.'];
        }
        if (! in_array($type, ['manual', 'auto', 'prerestore'], true)) {
            return ['ok' => false, 'error' => 'Jenis backup tidak valid.'];
        }
        if (! $this->acquireLock()) {
            return ['ok' => false, 'error' => 'Operasi backup/pemulihan lain sedang berjalan. Coba lagi sebentar lagi.'];
        }

        $this->prepareRuntime();
        $dir  = $this->ensureDir();
        $stem = sprintf('repo-%s-%s-%s', $type, date('Ymd-His'), bin2hex(random_bytes(4)));
        $tmp  = $dir . DIRECTORY_SEPARATOR . $stem . '.tmp';
        $sql  = $dir . DIRECTORY_SEPARATOR . $stem . '.sql.tmp';
        $final = $dir . DIRECTORY_SEPARATOR . $stem . '.zip';

        try {
            // 1) Dump database ke berkas sementara.
            $dump = $this->dumpDatabase($sql);

            // 2) Kumpulkan berkas unggahan.
            $files = [];
            $bytes = 0;
            if ($includeFiles) {
                foreach ($this->uploadFiles() as [$rel, $size, $abs]) {
                    $files[] = ['p' => $rel, 's' => $size, 'h' => (string) hash_file('sha256', $abs), 'abs' => $abs];
                    $bytes  += $size;
                }
            }

            $need = (int) (filesize($sql) + $bytes);
            $free = @disk_free_space($dir);
            if ($free !== false && $free < $need * 1.05 + 20 * 1024 * 1024) {
                throw new RuntimeException('Ruang disk tidak cukup. Dibutuhkan sekitar ' . format_bytes($need) . ', tersedia ' . format_bytes((int) $free) . '.');
            }

            $createdAt = date('c');
            $info = [
                'format'        => self::FORMAT,
                'app'           => $this->cfg->appName,
                'app_version'   => $this->cfg->appVersion,
                'created_at'    => $createdAt,
                'created_by'    => $by,
                'type'          => $type,
                'include_files' => $includeFiles,
                'tables'        => $dump['tables'],
                'rows'          => $dump['rows'],
                'file_count'    => count($files),
                'file_bytes'    => $bytes,
                'db_sha256'     => $dump['sha256'],
                'db_bytes'      => (int) filesize($sql),
                'php'           => PHP_VERSION,
            ];
            $manifest = $info + ['files' => array_map(static fn ($f) => ['p' => $f['p'], 's' => $f['s'], 'h' => $f['h']], $files)];

            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat berkas ZIP.');
            }
            $zip->addFromString('info.json', json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFile($sql, 'database.sql');
            foreach ($files as $f) {
                $zip->addFile($f['abs'], 'files/' . $f['p']);
                $zip->setCompressionName('files/' . $f['p'], ZipArchive::CM_STORE); // berkas sudah terkompresi
            }
            if (! $zip->close()) {
                throw new RuntimeException('Gagal menulis berkas ZIP (disk penuh atau izin tulis kurang).');
            }
            @unlink($sql);

            // Verifikasi cepat hasil tulis sebelum dinyatakan berhasil.
            $check = $this->inspect($tmp);
            if (! $check['ok']) {
                throw new RuntimeException('Verifikasi backup gagal: ' . $check['error']);
            }
            if (! @rename($tmp, $final)) {
                throw new RuntimeException('Gagal menyimpan berkas backup.');
            }
            @chmod($final, 0640);

            $this->prune();

            return ['ok' => true, 'name' => basename($final), 'size' => (int) filesize($final), 'files' => count($files), 'rows' => $dump['rows']];
        } catch (Throwable $e) {
            @unlink($tmp);
            @unlink($sql);
            log_message('error', 'Backup gagal: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $this->releaseLock();
        }
    }

    /** Hapus backup lama: jenis auto (sisakan N terbaru) dan prerestore (sisakan 3 terbaru). */
    public function prune(?int $keepAuto = null): int
    {
        $keep    = ['auto' => max(1, $keepAuto ?? $this->cfg->backupKeepAuto), 'prerestore' => 3];
        $removed = 0;
        $seen    = ['auto' => 0, 'prerestore' => 0];
        foreach ($this->list() as $b) {
            if (! isset($keep[$b['type']])) {
                continue;
            }
            if (++$seen[$b['type']] > $keep[$b['type']] && @unlink(self::dir() . DIRECTORY_SEPARATOR . $b['name'])) {
                $removed++;
            }
        }

        return $removed;
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && @unlink($path);
    }

    // ------------------------------------------------------------------
    // Impor ZIP dari luar (unggah / FTP) — hanya MENYIMPAN setelah lolos validasi
    // ------------------------------------------------------------------

    /** @return array{ok:bool, name?:string, error?:string} */
    public function import(string $sourcePath, bool $move = false): array
    {
        if (! self::available()) {
            return ['ok' => false, 'error' => 'Ekstensi PHP "zip" tidak aktif.'];
        }
        $check = $this->inspect($sourcePath);
        if (! $check['ok']) {
            return ['ok' => false, 'error' => $check['error']];
        }
        $dir  = $this->ensureDir();
        $name = sprintf('repo-upload-%s-%s.zip', date('Ymd-His'), bin2hex(random_bytes(4)));
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        $ok   = $move ? @rename($sourcePath, $dest) : @copy($sourcePath, $dest);
        if (! $ok) {
            return ['ok' => false, 'error' => 'Gagal menyimpan berkas ke folder backup.'];
        }
        @chmod($dest, 0640);

        return ['ok' => true, 'name' => $name];
    }

    /**
     * Impor ZIP yang ditaruh manual (mis. via FTP) di folder backup dengan nama bebas.
     *
     * @return array{imported:int, rejected:list<string>}
     */
    public function scanFolder(): array
    {
        $imported = 0;
        $rejected = [];
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $file) {
            if (self::isValidName(basename($file)) || ! is_file($file) || is_link($file)) {
                continue;
            }
            $res = $this->import($file, true);
            if ($res['ok']) {
                $imported++;
            } else {
                $rejected[] = basename($file) . ': ' . $res['error'];
            }
        }

        return ['imported' => $imported, 'rejected' => $rejected];
    }

    // ------------------------------------------------------------------
    // Validasi isi ZIP
    // ------------------------------------------------------------------

    /**
     * @return array{ok:true, manifest:array<string,mixed>, files:array<string,int>}|array{ok:false, error:string}
     */
    public function inspect(string $zipPath): array
    {
        $fail = static fn (string $m): array => ['ok' => false, 'error' => $m];

        if (! is_file($zipPath) || filesize($zipPath) < 100) {
            return $fail('Berkas kosong atau tidak ditemukan.');
        }
        $fh    = fopen($zipPath, 'rb');
        $magic = $fh ? fread($fh, 4) : '';
        $fh && fclose($fh);
        if ($magic !== "PK\x03\x04") {
            return $fail('Bukan berkas ZIP yang valid.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return $fail('ZIP rusak atau tidak dapat dibuka.');
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > $this->cfg->backupMaxEntries) {
                return $fail('Jumlah entri ZIP tidak wajar.');
            }

            $entries = []; // nama => ukuran asli
            $total   = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                if ($st === false) {
                    return $fail('Daftar isi ZIP rusak.');
                }
                $n = (string) $st['name'];
                if (str_ends_with($n, '/')) {
                    continue; // entri folder dilewati (tidak pernah dipakai)
                }
                if (! $this->safeEntryName($n)) {
                    return $fail('ZIP berisi nama entri yang tidak diizinkan.');
                }
                $opsys = $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    return $fail('ZIP berisi symlink, ditolak demi keamanan.');
                }
                if (isset($entries[$n])) {
                    return $fail('ZIP berisi entri ganda.');
                }
                $entries[$n] = (int) $st['size'];
                $total      += (int) $st['size'];
                if ($total > $this->cfg->backupMaxTotalMb * 1024 * 1024) {
                    return $fail('Ukuran isi ZIP melebihi batas ' . $this->cfg->backupMaxTotalMb . ' MB.');
                }
            }

            foreach (['info.json', 'manifest.json', 'database.sql'] as $req) {
                if (! isset($entries[$req])) {
                    return $fail('ZIP bukan backup aplikasi ini (' . $req . ' tidak ada).');
                }
            }
            if ($entries['manifest.json'] > 64 * 1024 * 1024) {
                return $fail('Manifest terlalu besar.');
            }

            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! isset($manifest['files'], $manifest['db_sha256'], $manifest['app_version'])) {
                return $fail('Manifest tidak valid atau format backup tidak didukung.');
            }
            if (($manifest['db_bytes'] ?? -1) !== $entries['database.sql']) {
                return $fail('Ukuran database.sql tidak sesuai manifest.');
            }

            $declared = [];
            foreach ((array) $manifest['files'] as $f) {
                if (! is_array($f) || ! isset($f['p'], $f['s'], $f['h']) || ! is_string($f['p']) || preg_match(self::STORED_RE, $f['p']) !== 1
                    || ! is_int($f['s']) || preg_match('/^[a-f0-9]{64}$/', (string) $f['h']) !== 1) {
                    return $fail('Manifest berisi entri berkas yang tidak valid.');
                }
                $declared['files/' . $f['p']] = $f['s'];
            }
            foreach ($entries as $n => $size) {
                if (str_starts_with($n, 'files/') && ($declared[$n] ?? null) !== $size) {
                    return $fail('Isi ZIP tidak cocok dengan manifest.');
                }
            }
            if (count($declared) !== count(array_filter(array_keys($entries), static fn ($n) => str_starts_with($n, 'files/')))) {
                return $fail('Jumlah berkas pada ZIP tidak cocok dengan manifest.');
            }

            return ['ok' => true, 'manifest' => $manifest, 'files' => $declared];
        } finally {
            $zip->close();
        }
    }

    private function safeEntryName(string $n): bool
    {
        if (in_array($n, ['info.json', 'manifest.json', 'database.sql'], true)) {
            return true;
        }

        return str_starts_with($n, 'files/') && preg_match(self::STORED_RE, substr($n, 6)) === 1;
    }

    // ------------------------------------------------------------------
    // Memulihkan
    // ------------------------------------------------------------------

    /**
     * @return array{ok:bool, error?:string, rolled_back?:bool, prerestore?:string, files?:int, rows?:int, missing?:int, migrated?:bool, warnings?:list<string>}
     */
    public function restore(string $name, ?string $by = null): array
    {
        $path = $this->path($name);
        if ($path === null) {
            return ['ok' => false, 'error' => 'Backup tidak ditemukan.'];
        }
        if (! self::available()) {
            return ['ok' => false, 'error' => 'Ekstensi PHP "zip" tidak aktif.'];
        }

        // 1) Validasi penuh SEBELUM menyentuh data apa pun.
        $check = $this->inspect($path);
        if (! $check['ok']) {
            return ['ok' => false, 'error' => $check['error']];
        }
        $manifest = $check['manifest'];
        if (version_compare((string) $manifest['app_version'], $this->cfg->appVersion, '>')) {
            return ['ok' => false, 'error' => 'Backup dibuat oleh versi aplikasi yang lebih baru (' . $manifest['app_version'] . '). Perbarui aplikasi lebih dulu.'];
        }
        if (! $this->acquireLock()) {
            return ['ok' => false, 'error' => 'Operasi backup/pemulihan lain sedang berjalan.'];
        }

        $this->prepareRuntime();
        $dir   = $this->ensureDir();
        $stage = $dir . DIRECTORY_SEPARATOR . 'stage-' . bin2hex(random_bytes(6));
        $zip   = null;

        try {
            $need = (int) ($manifest['db_bytes'] + $manifest['file_bytes']);
            $free = @disk_free_space($dir);
            if ($free !== false && $free < $need * 2.1 + 50 * 1024 * 1024) {
                throw new RuntimeException('Ruang disk tidak cukup untuk memulihkan (butuh sekitar ' . format_bytes((int) ($need * 2.1)) . ' termasuk backup pengaman).');
            }

            // 2) Ekstrak ke staging dengan verifikasi SHA-256 per berkas.
            if (! mkdir($stage . DIRECTORY_SEPARATOR . 'repository', 0750, true)) {
                throw new RuntimeException('Gagal membuat folder staging.');
            }
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('ZIP tidak dapat dibuka.');
            }
            $sqlFile = $stage . DIRECTORY_SEPARATOR . 'database.sql';
            $this->extractVerified($zip, 'database.sql', $sqlFile, (int) $manifest['db_bytes'], (string) $manifest['db_sha256']);
            foreach ($manifest['files'] as $f) {
                $dest = $stage . DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f['p']);
                if (! is_dir(dirname($dest)) && ! mkdir(dirname($dest), 0750, true)) {
                    throw new RuntimeException('Gagal membuat folder staging berkas.');
                }
                $this->extractVerified($zip, 'files/' . $f['p'], $dest, $f['s'], $f['h']);
            }
            $zip->close();
            $zip = null;

            // 3) Validasi SQL (daftar putih) sebelum eksekusi apa pun.
            $stmts = $this->validateSql($sqlFile);

            // 4) Backup pengaman (lepas kunci sementara agar create() bisa mengambilnya).
            $this->releaseLock();
            $pre = $this->create('prerestore', true, $by);
            if (! $pre['ok']) {
                throw new RuntimeException('Gagal membuat backup pengaman sebelum memulihkan: ' . ($pre['error'] ?? ''));
            }
            if (! $this->acquireLock()) {
                throw new RuntimeException('Gagal memperoleh kunci operasi.');
            }

            // 5) Terapkan database; bila gagal -> kembalikan dari backup pengaman.
            try {
                $this->execSql($sqlFile);
            } catch (Throwable $e) {
                $rolled = $this->rollbackFrom((string) $pre['name']);

                return ['ok' => false, 'error' => 'Pemulihan database gagal: ' . $e->getMessage() . ($rolled ? ' Data dikembalikan ke kondisi sebelum pemulihan.' : ' PERINGATAN: pengembalian otomatis gagal, pulihkan manual dari ' . $pre['name'] . '.'), 'rolled_back' => $rolled, 'prerestore' => $pre['name']];
            }

            // 6) Tukar folder berkas (dilewati bila backup hanya berisi database: berkas aktif dibiarkan).
            try {
                if (! empty($manifest['include_files'])) {
                    $this->swapUploads($stage . DIRECTORY_SEPARATOR . 'repository');
                }
            } catch (Throwable $e) {
                $rolled = $this->rollbackFrom((string) $pre['name']);

                return ['ok' => false, 'error' => 'Pemulihan berkas gagal: ' . $e->getMessage() . ($rolled ? ' Data dikembalikan ke kondisi sebelum pemulihan.' : ' Pulihkan manual dari ' . $pre['name'] . '.'), 'rolled_back' => $rolled, 'prerestore' => $pre['name']];
            }

            // 7) Backup lama -> sesuaikan skema bila perlu, lalu cek integritas.
            $warnings = [];
            $migrated = false;
            try {
                $migrations = \Config\Services::migrations();
                $migrations->setNamespace('App');
                $migrated = (bool) $migrations->latest();
            } catch (Throwable $e) {
                $warnings[] = 'Migrasi skema otomatis gagal (' . $e->getMessage() . '). Jalankan: php spark migrate';
            }
            $missing = $this->countMissingFiles();
            if (empty($manifest['include_files'])) {
                $warnings[] = 'Backup ini hanya berisi database; berkas unggahan yang ada tidak diubah.';
            }
            if ($missing > 0) {
                $warnings[] = $missing . ' catatan berkas tidak memiliki berkas fisik (backup tanpa berkas, atau berkas hilang saat backup dibuat).';
            }

            return [
                'ok'         => true,
                'prerestore' => $pre['name'],
                'files'      => count($manifest['files']),
                'rows'       => (int) ($manifest['rows'] ?? 0),
                'missing'    => $missing,
                'migrated'   => $migrated,
                'warnings'   => $warnings,
                'statements' => $stmts,
            ];
        } catch (Throwable $e) {
            log_message('error', 'Pemulihan gagal: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if ($zip instanceof ZipArchive) {
                @$zip->close();
            }
            $this->rmTree($stage);
            $this->releaseLock();
        }
    }

    /** Kembalikan DB + berkas dari backup pengaman. Tidak membuat backup baru. */
    private function rollbackFrom(string $name): bool
    {
        $path = $this->path($name);
        if ($path === null) {
            return false;
        }
        $stage = self::dir() . DIRECTORY_SEPARATOR . 'stage-rb-' . bin2hex(random_bytes(4));
        $zip   = new ZipArchive();
        try {
            $check = $this->inspect($path);
            if (! $check['ok'] || $zip->open($path, ZipArchive::RDONLY) !== true) {
                return false;
            }
            $m = $check['manifest'];
            mkdir($stage . DIRECTORY_SEPARATOR . 'repository', 0750, true);
            $sql = $stage . DIRECTORY_SEPARATOR . 'database.sql';
            $this->extractVerified($zip, 'database.sql', $sql, (int) $m['db_bytes'], (string) $m['db_sha256']);
            foreach ($m['files'] as $f) {
                $dest = $stage . DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $f['p']);
                if (! is_dir(dirname($dest))) {
                    mkdir(dirname($dest), 0750, true);
                }
                $this->extractVerified($zip, 'files/' . $f['p'], $dest, $f['s'], $f['h']);
            }
            $this->validateSql($sql);
            $this->execSql($sql);
            if (! empty($m['include_files'])) {
                $this->swapUploads($stage . DIRECTORY_SEPARATOR . 'repository');
            }

            return true;
        } catch (Throwable $e) {
            log_message('critical', 'Rollback pemulihan gagal: ' . $e->getMessage());

            return false;
        } finally {
            @$zip->close();
            $this->rmTree($stage);
        }
    }

    private function extractVerified(ZipArchive $zip, string $entry, string $dest, int $size, string $sha): void
    {
        $in = $zip->getStream($entry);
        if ($in === false) {
            throw new RuntimeException('Gagal membaca entri ' . $entry . ' dari ZIP.');
        }
        $out = fopen($dest, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException('Gagal menulis berkas staging.');
        }
        $ctx = hash_init('sha256');
        $len = 0;
        while (! feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === false) {
                break;
            }
            $len += strlen($chunk);
            if ($len > $size) {
                break; // lebih besar dari yang dideklarasikan -> tolak
            }
            hash_update($ctx, $chunk);
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);
        if ($len !== $size || ! hash_equals($sha, hash_final($ctx))) {
            @unlink($dest);
            throw new RuntimeException('Verifikasi integritas gagal untuk ' . $entry . ' (backup rusak atau diubah).');
        }
        @chmod($dest, 0640);
    }

    /** Ganti folder uploads/repository dengan $newDir (atomik per rename; dapat dikembalikan). */
    private function swapUploads(string $newDir): void
    {
        $base = Uploader::baseDir();
        $old  = $base . '.old-' . bin2hex(random_bytes(4));
        $had  = is_dir($base);

        if (! is_dir(dirname($base)) && ! mkdir(dirname($base), 0750, true)) {
            throw new RuntimeException('Folder uploads tidak dapat dibuat.');
        }
        if ($had && ! @rename($base, $old)) {
            throw new RuntimeException('Folder berkas aktif tidak dapat diganti (izin?).');
        }
        if (! @rename($newDir, $base)) {
            if ($had) {
                @rename($old, $base);
            }
            throw new RuntimeException('Folder berkas hasil pemulihan tidak dapat dipasang.');
        }
        if ($had) {
            $this->rmTree($old);
        }
    }

    private function countMissingFiles(): int
    {
        $missing = 0;
        $base    = Uploader::baseDir();
        $q       = db_connect()->table('files')->select('stored_name')->get();
        foreach ($q->getResultArray() as $r) {
            if (preg_match(self::STORED_RE, (string) $r['stored_name']) !== 1 || ! is_file($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $r['stored_name']))) {
                $missing++;
            }
        }

        return $missing;
    }

    // ------------------------------------------------------------------
    // Dump & eksekusi SQL
    // ------------------------------------------------------------------

    /** @return list<string> */
    private function tables(): array
    {
        return array_values(array_filter(db_connect()->listTables(), static fn ($t) => preg_match('/^[a-z0-9_]+$/', $t) === 1));
    }

    /** @return array{tables:int, rows:int, sha256:string} */
    private function dumpDatabase(string $file): array
    {
        $db   = db_connect();
        $fh   = fopen($file, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Gagal membuat berkas dump sementara.');
        }
        $ctx  = hash_init('sha256');
        $rows = 0;
        $w    = static function (string $line) use ($fh, $ctx): void {
            $line .= "\n";
            hash_update($ctx, $line);
            fwrite($fh, $line);
        };

        $w('-- Backup ' . date('c') . ' — jangan diubah');
        $w('SET FOREIGN_KEY_CHECKS=0;');
        $tables = $this->tables();
        foreach ($tables as $t) {
            $create = $db->query('SHOW CREATE TABLE `' . $t . '`')->getRowArray();
            $ddl    = (string) ($create['Create Table'] ?? '');
            if ($ddl === '') {
                throw new RuntimeException('Gagal membaca struktur tabel ' . $t . '.');
            }
            $w('DROP TABLE IF EXISTS `' . $t . '`;');
            $w(preg_replace('/\s*\n\s*/', ' ', $ddl) . ';');

            $pk = [];
            foreach ($db->query("SHOW KEYS FROM `{$t}` WHERE Key_name = 'PRIMARY'")->getResultArray() as $k) {
                $pk[(int) $k['Seq_in_index']] = '`' . str_replace('`', '', $k['Column_name']) . '`';
            }
            ksort($pk);
            $order  = $pk !== [] ? ' ORDER BY ' . implode(', ', $pk) : '';
            $offset = 0;
            $step   = 300;
            while (true) {
                $res = $db->query("SELECT * FROM `{$t}`{$order} LIMIT {$step} OFFSET {$offset}")->getResultArray();
                if ($res === []) {
                    break;
                }
                $cols = '`' . implode('`, `', array_map(static fn ($c) => str_replace('`', '', $c), array_keys($res[0]))) . '`';
                $vals = [];
                foreach ($res as $row) {
                    $vals[] = '(' . implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $db->escape((string) $v), $row)) . ')';
                }
                $w('INSERT INTO `' . $t . '` (' . $cols . ') VALUES ' . implode(', ', $vals) . ';');
                $rows   += count($res);
                $offset += $step;
                if (count($res) < $step) {
                    break;
                }
            }
        }
        $w('SET FOREIGN_KEY_CHECKS=1;');
        fclose($fh);

        return ['tables' => count($tables), 'rows' => $rows, 'sha256' => hash_final($ctx)];
    }

    /**
     * Validasi SELURUH berkas SQL terhadap daftar putih. Melempar exception pada baris pertama yang menyimpang.
     *
     * @return int jumlah pernyataan
     */
    private function validateSql(string $file): int
    {
        $allowed = array_flip($this->tables());
        $fh      = fopen($file, 'rb');
        if ($fh === false) {
            throw new RuntimeException('database.sql tidak dapat dibaca.');
        }
        $n = 0;
        $no = 0;
        while (($line = fgets($fh)) !== false) {
            $no++;
            if (strlen($line) > 16 * 1024 * 1024) {
                fclose($fh);
                throw new RuntimeException("Baris SQL #{$no} terlalu panjang.");
            }
            $line = rtrim($line, "\r\n");
            if ($line === '' || str_starts_with($line, '-- ')) {
                continue;
            }
            $ok = false;
            if ($line === 'SET FOREIGN_KEY_CHECKS=0;' || $line === 'SET FOREIGN_KEY_CHECKS=1;') {
                $ok = true;
            } elseif (preg_match('/^DROP TABLE IF EXISTS `([a-z0-9_]+)`;$/', $line, $m)) {
                $ok = isset($allowed[$m[1]]);
            } elseif (preg_match('/^CREATE TABLE `([a-z0-9_]+)` \((.+)\) ENGINE=[A-Za-z0-9_]+[A-Za-z0-9_=, .\'-]*;$/', $line, $m)) {
                $ok = isset($allowed[$m[1]]) && ! str_contains($m[2], ';');
            } elseif (preg_match('/^INSERT INTO `([a-z0-9_]+)` \(`[a-z0-9_]+`(?:, `[a-z0-9_]+`)*\) VALUES \(.+\);$/s', $line, $m)) {
                $ok = isset($allowed[$m[1]]);
            }
            if (! $ok) {
                fclose($fh);
                throw new RuntimeException("database.sql ditolak: baris #{$no} tidak termasuk pernyataan yang diizinkan.");
            }
            $n++;
        }
        fclose($fh);
        if ($n < 3) {
            throw new RuntimeException('database.sql kosong.');
        }

        return $n;
    }

    private function execSql(string $file): void
    {
        $db = db_connect();
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new RuntimeException('database.sql tidak dapat dibaca.');
        }
        try {
            while (($line = fgets($fh)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '-- ')) {
                    continue;
                }
                if ($db->simpleQuery($line) === false) {
                    $err = $db->error();
                    throw new RuntimeException('SQL error: ' . ($err['message'] ?? 'tidak diketahui'));
                }
            }
        } finally {
            fclose($fh);
            @$db->simpleQuery('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // ------------------------------------------------------------------
    // Utilitas
    // ------------------------------------------------------------------

    /** @return \Generator<int, array{0:string,1:int,2:string}> [relatif, ukuran, path absolut] */
    private function uploadFiles(): \Generator
    {
        $base = realpath(Uploader::baseDir());
        if ($base === false) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if (! $f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($base) + 1));
            if (preg_match(self::STORED_RE, $rel) === 1) {
                yield [$rel, (int) $f->getSize(), $f->getPathname()];
            }
        }
    }

    /** Baca satu entri kecil (JSON) dari ZIP; null bila tidak ada / tidak valid. */
    private function readSmallEntry(string $zipPath, string $entry): ?array
    {
        $zip = new ZipArchive();
        if (! self::available() || $zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $st   = $zip->statName($entry);
        $json = ($st !== false && $st['size'] <= 65536) ? $zip->getFromName($entry) : false;
        $zip->close();
        $data = $json !== false ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    private function ensureDir(): string
    {
        $dir = self::dir();
        if (! is_dir($dir) && ! mkdir($dir, 0750, true)) {
            throw new RuntimeException('Folder writable/backups tidak dapat dibuat.');
        }
        foreach (['.htaccess' => "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n", 'index.html' => "<!DOCTYPE html><title>403</title>\n"] as $f => $c) {
            if (! is_file($dir . DIRECTORY_SEPARATOR . $f)) {
                @file_put_contents($dir . DIRECTORY_SEPARATOR . $f, $c);
            }
        }

        return $dir;
    }

    private function prepareRuntime(): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
    }

    private function acquireLock(): bool
    {
        $dir = $this->ensureDir();
        $fh  = @fopen($dir . DIRECTORY_SEPARATOR . '.lock', 'c');
        if ($fh === false || ! flock($fh, LOCK_EX | LOCK_NB)) {
            $fh && fclose($fh);

            return false;
        }
        $this->lock = $fh;

        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
        $this->lock = null;
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
