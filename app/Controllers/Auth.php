<?php

namespace App\Controllers;

use App\Libraries\Captcha as CaptchaLib;
use App\Libraries\Throttle;
use App\Models\UserModel;

class Auth extends BaseController
{
    /** Hash bcrypt valid untuk menyamakan waktu respons saat username tidak ditemukan. */
    private const DUMMY_HASH = '$2y$10$Os37RUP1OQe/EFAatj0pLeHPKJWzFToCoFydgdE1LRQGnZUymVd02';

    public function index()
    {
        $auth = service('auth');

        return redirect()->to($auth->check() ? $auth->homeUrl() : site_url('login'));
    }

    public function login()
    {
        return view('auth/login', [
            'captcha' => (new CaptchaLib())->widgetData(),
        ]);
    }

    public function attempt()
    {
        $identifier = Throttle::normalize((string) $this->request->getPost('username'));
        $password   = (string) $this->request->getPost('password');
        $ip         = $this->request->getIPAddress();
        $throttle   = new Throttle();
        $back       = redirect()->to(site_url('login'))->with('old_username', mb_substr(trim((string) $this->request->getPost('username')), 0, 100));

        if ($identifier === '' || $password === '' || strlen($password) > 255) {
            return $back->with('error', 'Username/email dan password wajib diisi.');
        }

        // 1) Throttle: tolak lebih awal bila terkunci
        $wait = $throttle->retryAfter($identifier, $ip);
        if ($wait > 0) {
            log_activity('login_locked', 'Login ditolak (terkunci) untuk "' . $this->clean($identifier) . '"', ['id' => null, 'username' => $this->clean($identifier)]);

            return $back->with('error', 'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . max(1, (int) ceil($wait / 60)) . ' menit.');
        }

        // 2) Captcha "Saya bukan robot"
        if (! (new CaptchaLib())->validate($this->request)) {
            $throttle->hit($identifier, $ip, 'captcha');

            return $back->with('error', 'Verifikasi "Saya bukan robot" belum valid. Centang kotak verifikasi lalu coba lagi.');
        }

        // 3) Kredensial (selalu jalankan password_verify agar waktu respons seragam)
        $users = new UserModel();
        $user  = $users->findByLogin($identifier);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || ! $valid || (int) $user['is_active'] !== 1) {
            $throttle->hit($identifier, $ip, 'credential');
            log_activity('login_failed', 'Login gagal untuk "' . $this->clean($identifier) . '"', ['id' => $user['id'] ?? null, 'username' => $this->clean($identifier)]);

            return $back->with('error', 'Username/email atau password salah.');
        }

        // 4) Berhasil
        $throttle->clear($identifier, $ip);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $users->update($user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $auth = service('auth');
        $auth->login($user);
        log_activity('login', 'Login berhasil', $user);

        return redirect()->to($auth->homeUrl())->with('success', 'Selamat datang, ' . $user['name'] . '.');
    }

    public function logout()
    {
        log_activity('logout', 'Logout');
        service('auth')->logout();

        // Sesi baru untuk pesan flash setelah destroy.
        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar.');
    }

    private function clean(string $value): string
    {
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '', 0, 100);
    }
}
