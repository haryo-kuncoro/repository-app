<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Katalog hak akses (permission) dan peran bawaan untuk RBAC.
 *
 * Sumber tunggal: dipakai oleh seeder, form peran, dan pembuat SQL.
 * Menambah permission baru: tambahkan di sini, lalu jalankan seeder
 * (atau INSERT manual ke tabel `permissions`).
 */
class Rbac extends BaseConfig
{
    /**
     * key => [label, grup]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public array $permissions = [
        'dashboard.view'   => ['Melihat dashboard', 'Dashboard'],
        'files.view'       => ['Melihat daftar & detail berkas', 'Berkas'],
        'files.download'   => ['Mengunduh / pratinjau berkas', 'Berkas'],
        'files.create'     => ['Mengunggah berkas baru', 'Berkas'],
        'files.edit'       => ['Mengubah berkas milik sendiri', 'Berkas'],
        'files.delete'     => ['Menghapus berkas milik sendiri', 'Berkas'],
        'files.manage_all' => ['Mengelola SEMUA berkas (termasuk milik orang lain, berkas terbatas, tempat sampah)', 'Berkas'],
        'categories.manage' => ['Mengelola kategori berkas', 'Master Data'],
        'users.manage'     => ['Mengelola pengguna', 'Administrasi'],
        'roles.manage'     => ['Mengelola peran & hak akses', 'Administrasi'],
        'logs.view'        => ['Melihat log aktivitas', 'Administrasi'],
        'backup.manage'    => ['Membuat, mengunduh, mengunggah & menghapus backup (berisi seluruh data & berkas — sangat sensitif)', 'Administrasi'],
        'backup.restore'   => ['Memulihkan data dari backup (MENGGANTI seluruh database & berkas)', 'Administrasi'],
    ];

    /**
     * Peran bawaan. '*' berarti seluruh permission.
     * Peran dengan slug "superadmin" selalu memiliki semua permission.
     *
     * @var array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public array $roles = [
        'superadmin' => [
            'name'        => 'Super Admin',
            'description' => 'Akses penuh ke seluruh fitur, termasuk peran & hak akses.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name'        => 'Admin',
            'description' => 'Mengelola berkas, kategori, pengguna, dan melihat log.',
            'permissions' => [
                'dashboard.view', 'files.view', 'files.download', 'files.create', 'files.edit',
                'files.delete', 'files.manage_all', 'categories.manage', 'users.manage', 'logs.view',
            ],
        ],
        'operator' => [
            'name'        => 'Operator',
            'description' => 'Mengunggah dan mengelola berkas miliknya sendiri.',
            'permissions' => [
                'dashboard.view', 'files.view', 'files.download', 'files.create', 'files.edit', 'files.delete',
            ],
        ],
        'pengguna' => [
            'name'        => 'Pengguna',
            'description' => 'Hanya dapat melihat dan mengunduh berkas umum.',
            'permissions' => ['dashboard.view', 'files.view', 'files.download'],
        ],
    ];

    /** Kategori awal. */
    public array $categories = [
        ['Skripsi', 'skripsi', 'Skripsi, tugas akhir, dan tesis.', 'bi-mortarboard'],
        ['Akreditasi', 'akreditasi', 'Dokumen dan bukti pendukung akreditasi.', 'bi-patch-check'],
        ['Monev', 'monev', 'Monitoring dan evaluasi.', 'bi-clipboard-data'],
        ['Dokumen Penting', 'dokumen-penting', 'Dokumen penting lainnya.', 'bi-folder2-open'],
    ];
}
