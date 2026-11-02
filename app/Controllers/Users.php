<?php

namespace App\Controllers;

use App\Models\RoleModel;
use App\Models\UserModel;

class Users extends BaseController
{
    private UserModel $users;
    private RoleModel $roles;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->users = new UserModel();
        $this->roles = new RoleModel();
    }

    public function index()
    {
        return view('users/index', ['rows' => $this->users->listing()]);
    }

    public function create()
    {
        return view('users/form', ['u' => null, 'roles' => $this->roles->assignable(service('auth')->isSuperadmin())]);
    }

    public function store()
    {
        $rules = [
            'name'     => ['label' => 'Nama', 'rules' => 'required|max_length[100]'],
            'username' => ['label' => 'Username', 'rules' => 'required|min_length[4]|max_length[30]|alpha_dash|is_unique[users.username]'],
            'email'    => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[150]|is_unique[users.email]'],
            'role_id'  => ['label' => 'Peran', 'rules' => 'required|is_natural_no_zero|is_not_unique[roles.id]'],
        ];
        $errors = $this->validate($rules) ? [] : $this->validator->getErrors();

        $password = (string) $this->request->getPost('password');
        if ($policy = password_policy_error($password, (string) $this->request->getPost('username'))) {
            $errors['password'] = $policy;
        } elseif ($password !== (string) $this->request->getPost('password_confirm')) {
            $errors['password_confirm'] = 'Konfirmasi password tidak sama.';
        }

        $roleId = (int) $this->request->getPost('role_id');
        if (! isset($errors['role_id']) && ! $this->roleAllowed($roleId)) {
            $errors['role_id'] = 'Anda tidak berhak memberikan peran ini.';
        }
        if ($errors !== []) {
            return $this->fail('users/create', $errors);
        }

        $id = $this->users->insert([
            'role_id'              => $roleId,
            'name'                 => trim((string) $this->request->getPost('name')),
            'username'             => trim((string) $this->request->getPost('username')),
            'email'                => trim((string) $this->request->getPost('email')),
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'is_active'            => $this->request->getPost('is_active') ? 1 : 0,
            'must_change_password' => $this->request->getPost('must_change_password') ? 1 : 0,
            'password_changed_at'  => date('Y-m-d H:i:s'),
        ], true);

        log_activity('user_create', 'Menambah pengguna #' . $id . ' "' . trim((string) $this->request->getPost('username')) . '"');

        return $this->flash('users', 'success', 'Pengguna ditambahkan.');
    }

    public function edit(int $id)
    {
        $u = $this->target($id);

        return view('users/form', ['u' => $u, 'roles' => $this->roles->assignable(service('auth')->isSuperadmin())]);
    }

    public function update(int $id)
    {
        $u    = $this->target($id);
        $self = (int) $u['id'] === (int) $this->me()['id'];

        $rules = [
            'name'     => ['label' => 'Nama', 'rules' => 'required|max_length[100]'],
            'username' => ['label' => 'Username', 'rules' => 'required|min_length[4]|max_length[30]|alpha_dash|is_unique[users.username,id,' . $id . ']'],
            'email'    => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[150]|is_unique[users.email,id,' . $id . ']'],
            'role_id'  => ['label' => 'Peran', 'rules' => 'required|is_natural_no_zero|is_not_unique[roles.id]'],
        ];
        $errors = $this->validate($rules) ? [] : $this->validator->getErrors();

        $roleId   = $self ? (int) $u['role_id'] : (int) $this->request->getPost('role_id'); // tidak boleh ubah peran sendiri
        $isActive = $self ? 1 : ($this->request->getPost('is_active') ? 1 : 0);                // tidak boleh nonaktifkan diri sendiri

        if (! isset($errors['role_id']) && $roleId !== (int) $u['role_id'] && ! $this->roleAllowed($roleId)) {
            $errors['role_id'] = 'Anda tidak berhak memberikan peran ini.';
        }

        // Jangan sampai sistem tanpa Super Admin aktif.
        if ($u['role_slug'] === 'superadmin' && $this->users->activeSuperadmins($id) === 0) {
            $newRole = $this->roles->find($roleId);
            if ($isActive !== 1 || ($newRole['slug'] ?? '') !== 'superadmin') {
                $errors['role_id'] = 'Minimal harus ada satu Super Admin aktif.';
            }
        }

        $data     = [];
        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            if ($policy = password_policy_error($password, (string) $this->request->getPost('username'))) {
                $errors['password'] = $policy;
            } elseif ($password !== (string) $this->request->getPost('password_confirm')) {
                $errors['password_confirm'] = 'Konfirmasi password tidak sama.';
            } else {
                $data['password_hash']        = password_hash($password, PASSWORD_DEFAULT);
                $data['password_changed_at']  = date('Y-m-d H:i:s');
                $data['must_change_password'] = $self ? 0 : 1; // reset oleh admin => wajib ganti saat login
            }
        }

        if ($errors !== []) {
            return $this->fail('users/' . $id . '/edit', $errors);
        }

        $data += [
            'role_id'   => $roleId,
            'name'      => trim((string) $this->request->getPost('name')),
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'is_active' => $isActive,
        ];
        if (! isset($data['must_change_password']) && ! $self) {
            $data['must_change_password'] = $this->request->getPost('must_change_password') ? 1 : 0;
        }

        $this->users->update($id, $data);
        log_activity('user_update', 'Mengubah pengguna #' . $id . ' "' . $data['username'] . '"' . ($password !== '' ? ' (password direset)' : ''));

        return $this->flash('users', 'success', 'Pengguna diperbarui.');
    }

    public function delete(int $id)
    {
        $u = $this->target($id);

        if ((int) $u['id'] === (int) $this->me()['id']) {
            return $this->flash('users', 'error', 'Anda tidak dapat menghapus akun sendiri.');
        }
        if ($u['role_slug'] === 'superadmin' && $this->users->activeSuperadmins($id) === 0) {
            return $this->flash('users', 'error', 'Minimal harus ada satu Super Admin aktif.');
        }
        if (db_connect()->table('files')->where('uploaded_by', $id)->countAllResults() > 0) {
            return $this->flash('users', 'error', 'Pengguna masih memiliki berkas (termasuk di tempat sampah). Nonaktifkan akunnya sebagai gantinya.');
        }

        db_connect()->table('login_attempts')->where('identifier', mb_strtolower((string) $u['username']))->delete();
        $this->users->delete($id);
        log_activity('user_delete', 'Menghapus pengguna #' . $id . ' "' . $u['username'] . '"');

        return $this->flash('users', 'success', 'Pengguna dihapus.');
    }

    // ------------------------------------------------------------------

    /** Pengguna target beserta slug perannya. Non-superadmin tidak boleh menyentuh Super Admin. */
    private function target(int $id): array
    {
        $u = db_connect()->table('users u')->select('u.*, r.slug AS role_slug')
            ->join('roles r', 'r.id = u.role_id')->where('u.id', $id)->get()->getRowArray();

        if ($u === null || ($u['role_slug'] === 'superadmin' && ! service('auth')->isSuperadmin())) {
            $this->notFound();
        }

        return $u;
    }

    private function roleAllowed(int $roleId): bool
    {
        $role = $this->roles->find($roleId);

        return $role !== null && ($role['slug'] !== 'superadmin' || service('auth')->isSuperadmin());
    }

    private function fail(string $to, array $errors)
    {
        return redirect()->to(site_url($to))
            ->with('errors', $errors)
            ->with('old', $this->request->getPost(['name', 'username', 'email', 'role_id', 'is_active', 'must_change_password']) + ['_posted' => 1]);
    }
}
