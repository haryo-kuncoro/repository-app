<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Repository;

/**
 * Penyimpanan berkas yang aman.
 *
 *  - ekstensi diperiksa terhadap daftar putih
 *  - tipe MIME dibaca dari ISI berkas (finfo), bukan dari klaim browser
 *  - signature / magic bytes diperiksa
 *  - nama berkas di disk acak dengan ekstensi ".upl" dan disimpan di writable/ (di luar web root)
 *  - berkas hanya bisa diakses lewat controller (cek login + hak akses)
 */
class Uploader
{
    private const DANGEROUS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'jspx', 'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'dll',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'svg', 'htaccess', 'htpasswd', 'ini', 'vbs', 'ps1', 'jar',
    ];

    /** ekstensi => daftar MIME yang valid hasil deteksi isi. */
    private const MIME = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage', 'application/msword'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip', 'application/x-zip-compressed'],
        'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip', 'application/x-zip-compressed'],
        'odp'  => ['application/vnd.oasis.opendocument.presentation', 'application/zip', 'application/x-zip-compressed'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    private const ZIP_BASED = ['zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
    private const OLE_BASED = ['doc', 'xls', 'ppt'];

    private Repository $cfg;

    public function __construct(?Repository $cfg = null)
    {
        $this->cfg = $cfg ?? config(Repository::class);
    }

    public static function baseDir(): string
    {
        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'repository';
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error: string}
     */
    public function store(?UploadedFile $file): array
    {
        if ($file === null) {
            return $this->fail('Pilih berkas yang akan diunggah.');
        }
        if (! $file->isValid()) {
            return $this->fail($this->uploadError($file->getError()));
        }
        if ($file->hasMoved()) {
            return $this->fail('Berkas tidak valid.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            return $this->fail('Berkas kosong.');
        }
        if ($size > $this->cfg->maxUploadKb * 1024) {
            return $this->fail('Ukuran berkas melebihi batas ' . format_bytes($this->cfg->maxUploadKb * 1024) . '.');
        }

        $original = $this->cleanName($file->getClientName());
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === '' || ! in_array($ext, $this->cfg->allowedExtensions, true) || ! isset(self::MIME[$ext])) {
            return $this->fail('Jenis berkas tidak diizinkan. Diizinkan: ' . strtoupper(implode(', ', $this->cfg->allowedExtensions)) . '.');
        }
        // Hanya segmen SETELAH titik pertama (ekstensi) yang diperiksa; nama dasar "js.pdf" / "exe.zip" sah.
        foreach (array_slice(explode('.', strtolower($original)), 1) as $segment) {
            if (in_array($segment, self::DANGEROUS, true)) {
                return $this->fail('Nama berkas mengandung ekstensi yang tidak diizinkan.');
            }
        }

        $tmp  = $file->getTempName();
        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::MIME[$ext], true)) {
            return $this->fail('Isi berkas tidak sesuai dengan ekstensinya (.' . $ext . ').');
        }
        if (! $this->signatureOk($ext, $tmp)) {
            return $this->fail('Isi berkas tidak valid atau rusak.');
        }

        // Pemindaian virus/malware sebelum berkas disimpan permanen.
        $scan = (new VirusScanner($this->cfg))->scan($tmp, $ext);
        if (! $scan['ok']) {
            log_activity('virus_blocked', 'Unggahan ditolak [' . ($scan['signature'] ?? $scan['status']) . ']: ' . $original);

            return $this->fail($scan['message']);
        }

        $dir = self::baseDir() . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
            return $this->fail('Folder penyimpanan tidak dapat dibuat. Periksa izin folder writable/.');
        }

        $name = bin2hex(random_bytes(20)) . '.upl';
        try {
            $file->move($dir, $name);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal memindahkan upload: ' . $e->getMessage());

            return $this->fail('Berkas gagal disimpan. Periksa izin folder writable/.');
        }

        $full = $dir . DIRECTORY_SEPARATOR . $name;
        @chmod($full, 0640);

        return [
            'ok'   => true,
            'data' => [
                'original_name' => $original,
                'stored_name'   => date('Y') . '/' . date('m') . '/' . $name,
                'extension'     => $ext,
                'mime_type'     => $mime,
                'file_size'     => (int) filesize($full),
                'checksum'      => hash_file('sha256', $full),
                'scan_status'   => $scan['status'],
                'scan_engine'   => mb_substr($scan['engine'], 0, 80),
                'scanned_at'    => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /** Path absolut berkas, atau null bila nama tidak valid / berkas tidak ada. */
    public function path(string $stored): ?string
    {
        if (! preg_match('#^\d{4}/\d{2}/[a-f0-9]{40}\.upl$#', $stored)) {
            return null;
        }
        $base = realpath(self::baseDir());
        $full = realpath(self::baseDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stored));

        if ($base === false || $full === false || ! str_starts_with($full, $base . DIRECTORY_SEPARATOR) || ! is_file($full)) {
            return null;
        }

        return $full;
    }

    public function delete(string $stored): void
    {
        $path = $this->path($stored);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function signatureOk(string $ext, string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = (string) fread($fh, 8192);
        fclose($fh);

        if ($ext === 'pdf') {
            return str_contains(substr($head, 0, 1024), '%PDF-');
        }
        if (in_array($ext, self::ZIP_BASED, true)) {
            return str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06");
        }
        if (in_array($ext, self::OLE_BASED, true)) {
            return str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $info = @getimagesize($path);

            return $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
        }

        // txt / csv: tolak berkas biner
        return ! str_contains($head, "\0");
    }

    private function cleanName(string $name): string
    {
        $name = str_replace(['\\', '/'], '_', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name, " .\t\n\r");

        if (mb_strlen($name) > 200) {
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190) . ($ext !== '' ? '.' . $ext : '');
        }

        return $name === '' ? 'berkas' : $name;
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran berkas melebihi batas yang diizinkan server (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_PARTIAL                       => 'Berkas hanya terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE                       => 'Pilih berkas yang akan diunggah.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menyimpan berkas sementara.',
            default                                  => 'Berkas gagal diunggah.',
        };
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
