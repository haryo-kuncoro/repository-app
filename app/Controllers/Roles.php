<?php

namespace App\Controllers;

use App\Models\PermissionModel;
use App\Models\RoleModel;

class Roles extends BaseController
{
    private RoleModel $roles;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->roles = new RoleModel();
    }

    public function index()
    {
        return view('roles/index', ['rows' => $this->roles->listing()]);
    }

    public function create()
    {
        return view('roles/form', [
            'role'    => null,
            'groups'  => (new PermissionModel())->grouped(),
            'granted' => [],
            'mine'    => $this->grantable(),
        ]);
    }

    public function store()
    {
        $errors = $this->validateBasics(null);
        if ($errors !== []) {
            return $this->fail('roles/create', $errors);
        }

        $name = trim((string) $this->request->getPost('name'));
        $id   = $this->roles->insert([
            'name'        => $name,
            'slug'        => $this->uniqueSlug($name),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'is_system'   => 0,
        ], true);

        $this->roles->syncPermissions((int) $id, $this->postedPermissionIds());
        log_activity('role_create', 'Menambah peran #' . $id . ' "' . $name . '"');

        return $this->flash('roles', 'success', 'Peran ditambahkan.');
    }

    public function edit(int $id)
    {
        $role = $this->roles->find($id) ?? $this->notFound();

        return view('roles/form', [
            'role'    => $role,
            'groups'  => (new PermissionModel())->grouped(),
            'granted' => $this->roles->permissionIds($id),
            'mine'    => $this->grantable(),
        ]);
    }

    public function update(int $id)
    {
        $role = $this->roles->find($id) ?? $this->notFound();
        if ($role['slug'] === 'superadmin') {
            return $this->flash('roles', 'error', 'Peran Super Admin selalu memiliki semua hak akses dan tidak dapat diubah.');
        }

        $errors = $this->validateBasics($id);
        if ($errors !== []) {
            return $this->fail('roles/' . $id . '/edit', $errors);
        }

        $name = trim((string) $this->request->getPost('name'));
        $this->roles->update($id, [
            'name'        => $name,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
        ]);

        // Non-superadmin hanya boleh mengubah permission yang ia miliki sendiri;
        // permission lain yang sudah ada pada peran ini dipertahankan.
        $posted = $this->postedPermissionIds();
        if (! service('auth')->isSuperadmin()) {
            $mineIds = $this->grantableIds();
            $kept    = array_diff($this->roles->permissionIds($id), $mineIds);
            $posted  = array_merge($kept, array_intersect($posted, $mineIds));
        }
        $this->roles->syncPermissions($id, $posted);
        log_activity('role_update', 'Mengubah peran #' . $id . ' "' . $name . '" (' . count($posted) . ' hak akses)');

        return $this->flash('roles', 'success', 'Peran diperbarui.');
    }

    public function delete(int $id)
    {
        $role = $this->roles->find($id) ?? $this->notFound();

        if ((int) $role['is_system'] === 1 || $role['slug'] === 'superadmin') {
            return $this->flash('roles', 'error', 'Peran sistem tidak dapat dihapus.');
        }
        if ((int) db_connect()->table('users')->where('role_id', $id)->countAllResults() > 0) {
            return $this->flash('roles', 'error', 'Peran masih dipakai pengguna. Pindahkan pengguna ke peran lain terlebih dahulu.');
        }
        if ((int) $role['id'] === (int) $this->me()['role_id']) {
            return $this->flash('roles', 'error', 'Anda tidak dapat menghapus peran Anda sendiri.');
        }

        $this->roles->delete($id);
        log_activity('role_delete', 'Menghapus peran #' . $id . ' "' . $role['name'] . '"');

        return $this->flash('roles', 'success', 'Peran dihapus.');
    }

    // ------------------------------------------------------------------

    /** @return array<string,string> */
    private function validateBasics(?int $id): array
    {
        $rules = [
            'name'        => ['label' => 'Nama peran', 'rules' => 'required|max_length[60]|is_unique[roles.name,id,' . (int) $id . ']'],
            'description' => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        ];

        return $this->validate($rules) ? [] : $this->validator->getErrors();
    }

    /** @return list<int> permission ID yang valid dari form. */
    private function postedPermissionIds(): array
    {
        $posted = array_map('intval', (array) $this->request->getPost('permissions'));
        $valid  = array_map('intval', array_column(db_connect()->table('permissions')->select('id')->get()->getResultArray(), 'id'));
        $ids    = array_values(array_intersect($posted, $valid));

        if (! service('auth')->isSuperadmin()) {
            $ids = array_values(array_intersect($ids, $this->grantableIds()));
        }

        return $ids;
    }

    /** @return list<int> ID permission yang boleh diberikan oleh pengguna saat ini. */
    private function grantableIds(): array
    {
        $names = service('auth')->permissions();
        if ($names === []) {
            return [];
        }
        $rows = db_connect()->table('permissions')->select('id')->whereIn('name', $names)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'id'));
    }

    /** @return list<int> */
    private function grantable(): array
    {
        return service('auth')->isSuperadmin()
            ? array_map('intval', array_column(db_connect()->table('permissions')->select('id')->get()->getResultArray(), 'id'))
            : $this->grantableIds();
    }

    private function uniqueSlug(string $name): string
    {
        $base = url_title($name, '-', true) ?: 'peran';
        $slug = mb_substr($base, 0, 50);
        $i    = 2;
        while ($this->roles->where('slug', $slug)->countAllResults() > 0) {
            $slug = mb_substr($base, 0, 45) . '-' . $i++;
        }

        return $slug;
    }

    private function fail(string $to, array $errors)
    {
        return redirect()->to(site_url($to))->with('errors', $errors)
            ->with('old', $this->request->getPost(['name', 'description']) + ['permissions' => (array) $this->request->getPost('permissions')]);
    }
}
