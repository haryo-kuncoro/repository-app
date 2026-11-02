<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'role_id', 'name', 'username', 'email', 'password_hash', 'is_active',
        'must_change_password', 'password_changed_at', 'last_login_at', 'last_login_ip',
    ];

    /** Cari berdasarkan username ATAU email. */
    public function findByLogin(string $identifier): ?array
    {
        return $this->groupStart()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->groupEnd()
            ->first();
    }

    /** Daftar pengguna beserta peran dan jumlah berkas. */
    public function listing(): array
    {
        return $this->db->table('users u')
            ->select('u.id, u.name, u.username, u.email, u.is_active, u.must_change_password, u.last_login_at, r.name AS role_name, r.slug AS role_slug, (SELECT COUNT(*) FROM files f WHERE f.uploaded_by = u.id AND f.deleted_at IS NULL) AS file_count')
            ->join('roles r', 'r.id = u.role_id')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }

    /** Jumlah Super Admin aktif (untuk mencegah terkuncinya sistem). */
    public function activeSuperadmins(?int $exceptId = null): int
    {
        $b = $this->db->table('users u')->join('roles r', 'r.id = u.role_id')
            ->where('r.slug', 'superadmin')->where('u.is_active', 1);
        if ($exceptId !== null) {
            $b->where('u.id !=', $exceptId);
        }

        return $b->countAllResults();
    }
}
