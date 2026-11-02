<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;
use Config\Repository;

/**
 * Captcha ceklis "Saya bukan robot".
 *
 * Driver:
 *  - builtin   : ceklis internal. Server menerbitkan token sekali-pakai hanya bila
 *                (a) form sudah tampil minimal N detik (waktu dihitung SERVER),
 *                (b) ada interaksi pengguna nyata (pointer/keyboard/touch),
 *                (c) browser tidak mengaku sebagai WebDriver,
 *                (d) honeypot kosong. Token terikat sesi, kedaluwarsa, dipakai sekali.
 *  - recaptcha : Google reCAPTCHA v2 checkbox (verifikasi server-side).
 *  - turnstile : Cloudflare Turnstile (verifikasi server-side).
 *
 * Catatan jujur: driver builtin adalah penghalang untuk bot sederhana dan dilapisi
 * login throttle. Untuk perlindungan terhadap bot canggih, gunakan recaptcha/turnstile.
 */
class Captcha
{
    private Repository $cfg;

    public function __construct(?Repository $cfg = null)
    {
        $this->cfg = $cfg ?? config(Repository::class);
    }

    public function driver(): string
    {
        $driver = strtolower($this->cfg->captchaDriver);
        if (in_array($driver, ['recaptcha', 'turnstile'], true) && ($this->cfg->captchaSiteKey === '' || $this->cfg->captchaSecretKey === '')) {
            return 'builtin'; // kunci belum diisi -> jangan sampai login terkunci total
        }

        return in_array($driver, ['builtin', 'recaptcha', 'turnstile'], true) ? $driver : 'builtin';
    }

    /** Dipanggil setiap kali form login ditampilkan. */
    public function issue(): array
    {
        $state = [
            'nonce'       => bin2hex(random_bytes(16)),
            'issued'      => time(),
            'token'       => null,
            'verified_at' => 0,
            'tries'       => 0,
        ];
        session()->set('captcha', $state);

        return $state;
    }

    /** Data untuk view. */
    public function widgetData(): array
    {
        $driver = $this->driver();
        $data   = [
            'driver'  => $driver,
            'siteKey' => $this->cfg->captchaSiteKey,
            'nonce'   => '',
            'minSec'  => $this->cfg->captchaMinSeconds,
        ];
        if ($driver === 'builtin') {
            $data['nonce'] = $this->issue()['nonce'];
        }

        return $data;
    }

    /**
     * Endpoint AJAX untuk driver builtin.
     *
     * @return array{ok: bool, message?: string, token?: string, retry_after?: int}
     */
    public function verifyInteraction(array $in): array
    {
        if ($this->driver() !== 'builtin') {
            return ['ok' => false, 'message' => 'Driver captcha tidak mendukung verifikasi ini.'];
        }

        $state = session()->get('captcha');
        if (! is_array($state) || empty($state['nonce'])) {
            return ['ok' => false, 'message' => 'Sesi verifikasi tidak ditemukan. Muat ulang halaman.'];
        }

        $state['tries'] = (int) ($state['tries'] ?? 0) + 1;
        session()->set('captcha', $state);

        if ($state['tries'] > 10) {
            return ['ok' => false, 'message' => 'Terlalu banyak percobaan verifikasi. Muat ulang halaman.'];
        }
        if (! hash_equals((string) $state['nonce'], (string) ($in['nonce'] ?? ''))) {
            return ['ok' => false, 'message' => 'Verifikasi tidak valid. Muat ulang halaman.'];
        }

        $elapsed = time() - (int) $state['issued'];
        if ($elapsed < $this->cfg->captchaMinSeconds) {
            return [
                'ok'          => false,
                'message'     => 'Memverifikasi...',
                'retry_after' => $this->cfg->captchaMinSeconds - $elapsed + 1,
            ];
        }
        if (empty($in['human']) || ! empty($in['webdriver'])) {
            return ['ok' => false, 'message' => 'Verifikasi gagal. Gunakan browser biasa dan klik kotak ceklis.'];
        }

        $token                = bin2hex(random_bytes(24));
        $state['token']       = hash('sha256', $token);
        $state['verified_at'] = time();
        session()->set('captcha', $state);

        return ['ok' => true, 'token' => $token];
    }

    /** Validasi saat form login dikirim. Token builtin hanya berlaku sekali. */
    public function validate(IncomingRequest $request): bool
    {
        $driver = $this->driver();

        if ($driver === 'builtin') {
            // Honeypot: bot yang mengisi semua field akan ketahuan.
            if ((string) $request->getPost('website') !== '') {
                session()->remove('captcha');

                return false;
            }

            $state = session()->get('captcha');
            session()->remove('captcha'); // sekali pakai, berhasil atau tidak

            $token = (string) $request->getPost('captcha_token');
            if (! is_array($state) || empty($state['token']) || $token === '') {
                return false;
            }
            if (time() - (int) $state['verified_at'] > $this->cfg->captchaTtlSeconds) {
                return false;
            }

            return hash_equals((string) $state['token'], hash('sha256', $token));
        }

        $field = $driver === 'recaptcha' ? 'g-recaptcha-response' : 'cf-turnstile-response';
        $token = (string) $request->getPost($field);
        if ($token === '') {
            return false;
        }

        $url = $driver === 'recaptcha'
            ? 'https://www.google.com/recaptcha/api/siteverify'
            : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        try {
            $client   = \Config\Services::curlrequest(['timeout' => 6, 'http_errors' => false]);
            $response = $client->post($url, ['form_params' => [
                'secret'   => $this->cfg->captchaSecretKey,
                'response' => $token,
                'remoteip' => $request->getIPAddress(),
            ]]);
            $json = json_decode((string) $response->getBody(), true);

            return is_array($json) && ($json['success'] ?? false) === true;
        } catch (\Throwable $e) {
            log_message('error', 'Verifikasi captcha gagal: ' . $e->getMessage());

            return false; // gagal tertutup
        }
    }

    /**
     * Sumber tambahan untuk Content-Security-Policy sesuai driver.
     *
     * @return array<string, list<string>>
     */
    public function cspSources(): array
    {
        return match ($this->driver()) {
            'recaptcha' => [
                'script-src'  => ['https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'],
                'frame-src'   => ['https://www.google.com/recaptcha/', 'https://recaptcha.google.com/recaptcha/'],
                'connect-src' => ['https://www.google.com/recaptcha/'],
            ],
            'turnstile' => [
                'script-src' => ['https://challenges.cloudflare.com'],
                'frame-src'  => ['https://challenges.cloudflare.com'],
            ],
            default => [],
        };
    }
}
