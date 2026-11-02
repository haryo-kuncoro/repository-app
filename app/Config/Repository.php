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

    /** Pilihan visibilitas berkas. */
    public array $visibilities = [
        'umum'     => 'Umum (semua pengguna yang berhak melihat berkas)',
        'terbatas' => 'Terbatas (hanya pengunggah & pengelola)',
    ];
}
