<?php

namespace App\Libraries;

use Config\Repository;
use ZipArchive;

/**
 * Pemindai heuristik murni PHP (tanpa ekstensi/biner tambahan) — lapis pertahanan yang
 * selalu tersedia di shared hosting. BUKAN pengganti antivirus: ia mendeteksi pola
 * berbahaya yang umum (web shell, makro Office, JavaScript di PDF, executable di dalam
 * arsip, zip bomb, path traversal, berkas uji EICAR) tetapi tidak punya basis signature
 * malware. Untuk deteksi sesungguhnya gunakan ClamAV (lihat VirusScanner).
 */
class HeuristicScanner
{
    private const CHUNK     = 1048576; // 1 MB
    private const OVERLAP   = 64;
    private const OLE_EXT   = ['doc', 'xls', 'ppt'];
    private const ZIP_EXT   = ['zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
    private const OOXML     = ['docx', 'xlsx', 'pptx'];
    private const ODF       = ['odt', 'ods', 'odp'];
    private const MAX_ENTRIES      = 10000;
    private const MAX_TOTAL_BYTES  = 1073741824; // 1 GB tak terkompresi
    private const BOMB_ENTRY_BYTES = 52428800;   // 50 MB per entri
    private const BOMB_RATIO       = 1000;
    private const MAGIC_CHECK_MAX  = 2000;
    private const PDF_MAX_BYTES    = 67108864;   // 64 MB

    public function __construct(private Repository $cfg)
    {
    }

    /**
     * @return array{signature: string, detail: string}|null null = tidak ada temuan
     */
    public function scan(string $path, string $ext): ?array
    {
        $ext = strtolower($ext);

        $hit = $this->scanBytes($path, $ext);
        if ($hit !== null) {
            return $hit;
        }
        if ($ext === 'pdf') {
            $hit = $this->scanPdf($path);
            if ($hit !== null) {
                return $hit;
            }
        }
        if (in_array($ext, self::ZIP_EXT, true)) {
            return $this->scanZip($path, $ext);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Pola byte (semua tipe)
    // ------------------------------------------------------------------
    private function scanBytes(string $path, string $ext): ?array
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return $this->hit('Heuristic.Unreadable', 'berkas tidak dapat dibaca');
        }

        $isOle = in_array($ext, self::OLE_EXT, true);
        $isTxt = in_array($ext, ['txt', 'csv'], true);
        $vba   = "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T\0"; // "_VBA_PROJECT" UTF-16LE (direktori OLE)
        $tail  = '';

        try {
            while (! feof($fh)) {
                $chunk = fread($fh, self::CHUNK);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $buf = $tail . $chunk;

                if (stripos($buf, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') !== false) {
                    return $this->hit('Heuristic.EICAR-Test-File', 'berkas uji antivirus EICAR');
                }
                if (stripos($buf, '<?php') !== false || stripos($buf, '<script language="php"') !== false) {
                    return $this->hit('Heuristic.WebShell.PHP', 'berisi kode PHP tertanam');
                }
                if (stripos($buf, '<%eval') !== false || stripos($buf, '<%execute') !== false) {
                    return $this->hit('Heuristic.WebShell.ASP', 'berisi kode ASP tertanam');
                }
                if ($isTxt) {
                    if (str_contains($buf, '<?=')) {
                        return $this->hit('Heuristic.WebShell.PHP', 'berisi kode PHP tertanam');
                    }
                    if (preg_match('/(?:^|[\r\n,;\t"\'])\s*[=+@\-]\s*(?:cmd|powershell|mshta|rundll32|msiexec|regsvr32)\b[^\r\n]*\|/i', $buf) === 1) {
                        return $this->hit('Heuristic.CSV.DDE', 'rumus spreadsheet berbahaya (DDE/CSV injection)');
                    }
                }
                if ($isOle && $this->cfg->scanBlockMacros && str_contains($buf, $vba)) {
                    return $this->hit('Heuristic.Office.Macro', 'dokumen berisi makro VBA');
                }

                $tail = substr($buf, -self::OVERLAP);
            }
        } finally {
            fclose($fh);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // PDF: periksa kamus objek (di luar stream terkompresi agar tidak false positive)
    // ------------------------------------------------------------------
    private function scanPdf(string $path): ?array
    {
        if (! $this->cfg->scanBlockActivePdf) {
            return null;
        }
        $size = @filesize($path);
        if ($size === false || $size > self::PDF_MAX_BYTES) {
            return null;
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        // Buang isi "stream ... endstream" (data biner), sisakan kamus objek.
        $out = '';
        $pos = 0;
        $len = strlen($data);
        while ($pos < $len) {
            $s = strpos($data, 'stream', $pos);
            if ($s === false) {
                $out .= substr($data, $pos);
                break;
            }
            if ($s >= 3 && substr($data, $s - 3, 3) === 'end') {
                $out .= substr($data, $pos, $s + 6 - $pos);
                $pos  = $s + 6;
                continue;
            }
            $next = substr($data, $s + 6, 2);
            if (! str_starts_with($next, "\n") && ! str_starts_with($next, "\r")) {
                $out .= substr($data, $pos, $s + 6 - $pos);
                $pos  = $s + 6;
                continue;
            }
            $out .= substr($data, $pos, $s - $pos);
            $e    = strpos($data, 'endstream', $s + 6);
            if ($e === false) {
                break;
            }
            $pos = $e + 9;
        }
        unset($data);

        // Dekode penyamaran nama PDF: /J#61vaScript -> /JavaScript
        $out = preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $m): string => chr((int) hexdec($m[1])), $out) ?? $out;

        if (preg_match('/\/(JavaScript|JS|Launch|EmbeddedFile|RichMedia)(?![A-Za-z0-9])/', $out, $m) === 1) {
            return $this->hit(
                'Heuristic.PDF.Active.' . $m[1],
                'PDF memuat konten aktif (/' . $m[1] . ') yang sering dipakai malware'
            );
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Arsip: zip, docx, xlsx, pptx, odt, ods, odp
    // ------------------------------------------------------------------
    private function scanZip(string $path, string $ext): ?array
    {
        if (! class_exists(ZipArchive::class)) {
            return null; // ekstensi zip tidak tersedia di hosting ini
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return $this->hit('Heuristic.Archive.Corrupt', 'arsip rusak atau tidak dapat dibuka');
        }

        try {
            $count = $zip->numFiles;
            if ($count > self::MAX_ENTRIES) {
                return $this->hit('Heuristic.Archive.TooManyEntries', 'arsip berisi terlalu banyak berkas (' . $count . ')');
            }

            // Validasi struktur (mencegah zip sembarang yang disamarkan jadi dokumen)
            if (in_array($ext, self::OOXML, true) && $zip->locateName('[Content_Types].xml') === false) {
                return $this->hit('Heuristic.Structure.Invalid', 'struktur dokumen Office tidak valid');
            }
            if (in_array($ext, self::ODF, true)) {
                $mime = (string) $zip->getFromName('mimetype');
                if (! str_starts_with($mime, 'application/vnd.oasis.opendocument')) {
                    return $this->hit('Heuristic.Structure.Invalid', 'struktur dokumen OpenDocument tidak valid');
                }
            }

            $blocked = array_map('strtolower', $this->cfg->scanBlockedInArchive);
            $total   = 0;

            for ($i = 0; $i < $count; $i++) {
                $st = $zip->statIndex($i);
                if ($st === false) {
                    return $this->hit('Heuristic.Archive.Corrupt', 'entri arsip rusak');
                }
                $name = (string) $st['name'];
                $size = (int) $st['size'];
                $comp = (int) $st['comp_size'];

                if (str_contains($name, "\0") || str_contains($name, '../') || str_contains($name, '..\\')
                    || str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('#^[A-Za-z]:#', $name) === 1) {
                    return $this->hit('Heuristic.Archive.PathTraversal', 'nama berkas dalam arsip berbahaya (' . $this->short($name) . ')');
                }
                if ($this->cfg->scanBlockEncryptedArchives && (int) ($st['encryption_method'] ?? 0) !== 0) {
                    return $this->hit('Heuristic.Archive.Encrypted', 'arsip terenkripsi tidak dapat dipindai');
                }

                $total += $size;
                if ($total > self::MAX_TOTAL_BYTES
                    || ($size > self::BOMB_ENTRY_BYTES && $comp > 0 && intdiv($size, $comp) > self::BOMB_RATIO)) {
                    return $this->hit('Heuristic.Archive.Bomb', 'arsip terindikasi zip bomb');
                }

                $isDir = str_ends_with($name, '/');
                if ($isDir) {
                    continue;
                }

                $entryExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($entryExt !== '' && in_array($entryExt, $blocked, true)) {
                    return $this->hit('Heuristic.Archive.BlockedFile', 'arsip berisi berkas terlarang (' . $this->short($name) . ')');
                }

                if ($this->cfg->scanBlockMacros) {
                    if (in_array($ext, self::OOXML, true) && preg_match('#(^|/)vbaProject\.bin$#i', $name) === 1) {
                        return $this->hit('Heuristic.Office.Macro', 'dokumen berisi makro VBA');
                    }
                    if (in_array($ext, self::ODF, true) && preg_match('#^(Basic|Scripts)/#', $name) === 1) {
                        return $this->hit('Heuristic.Office.Macro', 'dokumen berisi makro/skrip');
                    }
                }

                // Periksa byte awal isi entri: executable yang diganti namanya, web shell
                if ($i < self::MAGIC_CHECK_MAX && $size >= 4 && (int) ($st['encryption_method'] ?? 0) === 0) {
                    $fp = $zip->getStream($name);
                    if ($fp !== false) {
                        $head = (string) fread($fp, 5);
                        fclose($fp);
                        if (str_starts_with($head, 'MZ')) {
                            return $this->hit('Heuristic.Archive.Executable.PE', 'arsip berisi program Windows (' . $this->short($name) . ')');
                        }
                        if (str_starts_with($head, "\x7fELF")) {
                            return $this->hit('Heuristic.Archive.Executable.ELF', 'arsip berisi program Linux (' . $this->short($name) . ')');
                        }
                        if (strcasecmp($head, '<?php') === 0) {
                            return $this->hit('Heuristic.Archive.WebShell.PHP', 'arsip berisi skrip PHP (' . $this->short($name) . ')');
                        }
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    private function short(string $name): string
    {
        $name = preg_replace('/[^\P{C}]+/u', '?', $name) ?? '';

        return mb_substr($name, 0, 80);
    }

    /** @return array{signature: string, detail: string} */
    private function hit(string $signature, string $detail): array
    {
        return ['signature' => $signature, 'detail' => $detail];
    }
}
