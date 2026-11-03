<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Pengaturan khusus aplikasi E-Repository.
 *
 * Semua nilai skalar dapat di-override lewat file .env, contoh:
 *   repository.appName = "Repositori Prodi Informatika"
 *   repository.maxUploadKb = 10240
 *   repository.captchaDriver = "turnstile"
 */
class Repository extends BaseConfig
{
    // ------------------------------------------------------------------
    // Identitas aplikasi
    // ------------------------------------------------------------------
    public string $appName     = 'E-Repository';
    public string $institution = 'Nama Institusi Anda';

    /** Password awal akun admin dari seeder / SQL. WAJIB diganti saat login pertama. */
    public string $defaultAdminPassword = 'Admin@Repo2026!';

    // ------------------------------------------------------------------
    // Login throttle (anti brute force)
    // ------------------------------------------------------------------
    /** Maks. gagal login untuk kombinasi username + IP dalam jendela waktu. */
    public int $maxAttemptsPerAccount = 5;

    /** Maks. gagal login untuk satu username dari semua IP (serangan terdistribusi). */
    public int $maxAttemptsPerIdentifier = 15;

    /** Maks. percobaan gagal (login/captcha) dari satu IP. */
    public int $maxAttemptsPerIp = 20;

    /** Lama jendela hitung sekaligus lama penguncian (menit). */
    public int $lockMinutes = 15;

    // ------------------------------------------------------------------
    // Kebijakan password
    // ------------------------------------------------------------------
    public int $passwordMinLength = 10;

    // ------------------------------------------------------------------
    // Captcha "Saya bukan robot"
    //   builtin   : ceklis internal (tanpa layanan pihak ketiga, langsung jalan)
    //   recaptcha : Google reCAPTCHA v2 checkbox  (butuh site key & secret key)
    //   turnstile : Cloudflare Turnstile          (butuh site key & secret key)
    // ------------------------------------------------------------------
    public string $captchaDriver    = 'builtin';
    public string $captchaSiteKey   = '';
    public string $captchaSecretKey = '';

    /** Waktu minimal (detik) sejak form tampil sebelum ceklis dianggap valid. */
    public int $captchaMinSeconds = 2;

    /** Masa berlaku token ceklis (detik). */
    public int $captchaTtlSeconds = 300;

    // ------------------------------------------------------------------
    // Unggah berkas
    // ------------------------------------------------------------------
    /** Ukuran maksimum per berkas (KB). Pastikan <= upload_max_filesize di hosting. */
    public int $maxUploadKb = 20480;

    /** Ekstensi yang diizinkan (dicek juga terhadap isi/MIME sebenarnya). */
    public array $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'txt', 'csv', 'zip', 'jpg', 'jpeg', 'png',
    ];

    // ------------------------------------------------------------------
    // Pemindai virus / malware untuk setiap unggahan
    //   auto    : ClamAV bila terdeteksi + pemindai heuristik bawaan (disarankan)
    //   clamav  : ClamAV WAJIB; unggahan DITOLAK bila ClamAV tidak tersedia
    //   builtin : hanya pemindai heuristik bawaan PHP
    //   off     : tanpa pemindaian (tidak disarankan)
    // ------------------------------------------------------------------
    public string $scanMode = 'auto';

    /** Path socket clamd (kosong = auto-deteksi lokasi umum). */
    public string $clamdSocket = '';

    /** Host clamd via TCP, mis. 127.0.0.1 atau server ClamAV khusus (kosong = tidak dipakai). */
    public string $clamdHost = '';
    public int $clamdPort    = 3310;

    /** Path biner clamscan (kosong = auto-deteksi). Berat: memuat database virus tiap pemindaian. */
    public string $clamscanPath = '';

    /** Folder database signature kustom untuk clamscan (opsional, opsi -d). */
    public string $clamDatabaseDir = '';

    /** Batas waktu pemindaian ClamAV (detik). */
    public int $scanTimeout = 60;

    /** Tolak dokumen Office/ODF yang berisi makro (VBA/Basic). */
    public bool $scanBlockMacros = true;

    /** Tolak PDF yang memuat JavaScript / Launch / berkas tertanam. */
    public bool $scanBlockActivePdf = true;

    /** Tolak arsip ZIP terenkripsi (isinya tidak dapat dipindai). */
    public bool $scanBlockEncryptedArchives = true;

    /** Ekstensi yang tidak boleh ada di dalam arsip (zip/docx/dst.). */
    public array $scanBlockedInArchive = [
        'exe', 'dll', 'scr', 'bat', 'cmd', 'com', 'msi', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'hta',
        'cpl', 'pif', 'lnk', 'jar', 'ps1', 'reg', 'sh', 'asp', 'aspx', 'jsp',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
    ];

    /** Pilihan visibilitas berkas. */
    public array $visibilities = [
        'umum'     => 'Umum (semua pengguna yang berhak melihat berkas)',
        'terbatas' => 'Terbatas (hanya pengunggah & pengelola)',
    ];
}
