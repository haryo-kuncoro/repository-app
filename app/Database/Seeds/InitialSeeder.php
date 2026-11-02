<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Rbac;
use Config\Repository;

/**
 * Mengisi permission, peran bawaan, kategori awal, dan akun admin pertama.
 * Aman dijalankan berulang kali (tidak membuat duplikat).
 *
 *   php spark db:seed InitialSeeder
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $rbac = config(Rbac::class);
        $repo = config(Repository::class);
        $now  = date('Y-m-d H:i:s');

        // permissions
        foreach ($rbac->permissions as $key => [$label, $group]) {
            $exists = $this->db->table('permissions')->where('name', $key)->countAllResults();
            if ($exists === 0) {
                $this->db->table('permissions')->insert(['name' => $key, 'label' => $label, 'group_name' => $group]);
            } else {
                $this->db->table('permissions')->where('name', $key)->update(['label' => $label, 'group_name' => $group]);
            }
        }

        $permIds = array_column(
            $this->db->table('permissions')->select('id, name')->get()->getResultArray(),
            'id',
            'name'
        );

        // roles + role_permissions
        foreach ($rbac->roles as $slug => $def) {
            $role = $this->db->table('roles')->where('slug', $slug)->get()->getRowArray();
            if ($role === null) {
                $this->db->table('roles')->insert([
                    'name' => $def['name'], 'slug' => $slug, 'description' => $def['description'],
                    'is_system' => $slug === 'superadmin' ? 1 : 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $roleId = (int) $this->db->insertID();
            } else {
                $roleId = (int) $role['id'];
            }

            // Hanya isi izin awal untuk peran yang BARU dibuat agar tidak menimpa penyesuaian admin.
            if ($role === null) {
                $names = in_array('*', $def['permissions'], true) ? array_keys($rbac->permissions) : $def['permissions'];
                foreach ($names as $name) {
                    if (isset($permIds[$name])) {
                        $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permIds[$name]]);
                    }
                }
            } elseif ($slug === 'superadmin') {
                // Super Admin selalu memiliki semua permission (termasuk yang baru ditambahkan).
                foreach ($permIds as $pid) {
                    $has = $this->db->table('role_permissions')->where(['role_id' => $roleId, 'permission_id' => $pid])->countAllResults();
                    if ($has === 0) {
                        $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
                    }
                }
            }
        }

        // categories
        foreach ($rbac->categories as [$name, $slug, $desc, $icon]) {
            if ($this->db->table('categories')->where('slug', $slug)->countAllResults() === 0) {
                $this->db->table('categories')->insert([
                    'name' => $name, 'slug' => $slug, 'description' => $desc, 'icon' => $icon,
                    'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // admin pertama
        if ($this->db->table('users')->where('username', 'admin')->countAllResults() === 0) {
            $superId = (int) $this->db->table('roles')->where('slug', 'superadmin')->get()->getRow('id');
            $this->db->table('users')->insert([
                'role_id'              => $superId,
                'name'                 => 'Administrator',
                'username'             => 'admin',
                'email'                => 'admin@example.com',
                'password_hash'        => password_hash($repo->defaultAdminPassword, PASSWORD_DEFAULT),
                'is_active'            => 1,
                'must_change_password' => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }
}
