<?php

namespace App\Libraries;

/**
 * Status login + RBAC untuk request berjalan.
 *
 * Data pengguna dan hak akses selalu dibaca dari database pada tiap request,
 * sehingga penonaktifan akun atau perubahan hak akses langsung berlaku.
 */
class Auth
{
    private const KEY = 'auth_uid';

    private ?array $user        = null;
    private ?array $permissions = null;
    private bool $resolved      = false;

    /** Tandai pengguna sebagai login (dipanggil setelah kredensial valid). */
    public function login(array $user): void
    {
        $session = session();
        $session->regenerate(true); // cegah session fixation

        $session->set([
            self::KEY   => (int) $user['id'],
            'auth_ua'   => $this->agentHash(),
            'auth_time' => time(),
        ]);

        $this->reset();

        db_connect()->table('users')->where('id', (int) $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => mb_substr((string) service('request')->getIPAddress(), 0, 45),
        ]);
    }

    public function logout(): void
    {
        $session = session();
        $session->remove([self::KEY, 'auth_ua', 'auth_time']);
        $session->destroy();
        $this->reset();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * Pengguna aktif beserta nama & slug perannya, atau null bila belum login /
     * akun dinonaktifkan / sesi tidak cocok.
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $session = session();
        $id      = (int) $session->get(self::KEY);
        if ($id < 1) {
            return null;
        }

        // Ikat sesi ke User-Agent: cookie yang dicuri & dipakai di browser lain ditolak.
        if (! hash_equals((string) $session->get('auth_ua'), $this->agentHash())) {
            $session->remove([self::KEY, 'auth_ua', 'auth_time']);

            return null;
        }

        $row = db_connect()->table('users u')
            ->select('u.*, r.slug AS role_slug, r.name AS role_name')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)
            ->get()->getRowArray();

        if ($row === null || (int) $row['is_active'] !== 1) {
            $session->remove([self::KEY, 'auth_ua', 'auth_time']);

            return null;
        }

        unset($row['password_hash']); // tidak perlu beredar di seluruh aplikasi

        return $this->user = $row;
    }

    public function isSuperadmin(): bool
    {
        $user = $this->user();

        return $user !== null && $user['role_slug'] === 'superadmin';
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $user = $this->user();
        if ($user === null) {
            return $this->permissions = [];
        }

        $builder = db_connect()->table('permissions p')->select('p.name');
        if (! $this->isSuperadmin()) {
            $builder->join('role_permissions rp', 'rp.permission_id = p.id')->where('rp.role_id', (int) $user['role_id']);
        }

        return $this->permissions = array_column($builder->get()->getResultArray(), 'name');
    }

    /** True bila pengguna punya SALAH SATU permission yang diminta. */
    public function can(string ...$permissions): bool
    {
        if (! $this->check()) {
            return false;
        }
        if ($this->isSuperadmin()) {
            return true;
        }

        return array_intersect($permissions, $this->permissions()) !== [];
    }

    /** Halaman tujuan setelah login sesuai hak akses. */
    public function homeUrl(): string
    {
        if ($this->can('dashboard.view')) {
            return site_url('dashboard');
        }
        if ($this->can('files.view')) {
            return site_url('files');
        }

        return site_url('profile');
    }

    private function reset(): void
    {
        $this->user        = null;
        $this->permissions = null;
        $this->resolved    = false;
    }

    private function agentHash(): string
    {
        $request = service('request');
        $agent   = method_exists($request, 'getUserAgent') ? (string) $request->getUserAgent()->getAgentString() : '';

        return hash('sha256', $agent);
    }
}
