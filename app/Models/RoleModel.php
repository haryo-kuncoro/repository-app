<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'slug', 'description', 'is_system'];

    /** Peran beserta jumlah pengguna & permission. */
    public function listing(): array
    {
        return $this->db->table('roles r')
            ->select('r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count, (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count')
            ->orderBy('r.id', 'ASC')
            ->get()->getResultArray();
    }

    /** @return list<int> */
    public function permissionIds(int $roleId): array
    {
        return array_map('intval', array_column(
            $this->db->table('role_permissions')->select('permission_id')->where('role_id', $roleId)->get()->getResultArray(),
            'permission_id'
        ));
    }

    /** Ganti seluruh permission suatu peran. @param list<int> $permissionIds */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->transStart();
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissionIds) as $pid) {
            $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => (int) $pid]);
        }
        $this->db->transComplete();
    }

    /** Peran yang boleh diberikan oleh $actorIsSuperadmin. */
    public function assignable(bool $actorIsSuperadmin): array
    {
        $b = $this->orderBy('id', 'ASC');
        if (! $actorIsSuperadmin) {
            $b->where('slug !=', 'superadmin');
        }

        return $b->findAll();
    }
}
