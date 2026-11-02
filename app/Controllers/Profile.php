<?php

namespace App\Controllers;

use App\Models\UserModel;

class Profile extends BaseController
{
    public function index()
    {
        return view('profile/index', ['u' => $this->me()]);
    }

    public function update()
    {
        $me    = $this->me();
        $rules = [
            'name'  => ['label' => 'Nama', 'rules' => 'required|max_length[100]'],
            'email' => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[150]|is_unique[users.email,id,' . (int) $me['id'] . ']'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('profile'))
                ->with('errors', $this->validator->getErrors())
                ->with('old', $this->request->getPost(['name', 'email']));
        }

        (new UserModel())->update($me['id'], [
            'name'  => trim((string) $this->request->getPost('name')),
            'email' => trim((string) $this->request->getPost('email')),
        ]);
        log_activity('profile_update', 'Memperbarui profil');

        return $this->flash('profile', 'success', 'Profil berhasil diperbarui.');
    }

    public function password()
    {
        return view('profile/password', ['u' => $this->me()]);
    }

    public function updatePassword()
    {
        $me       = $this->me();
        $current  = (string) $this->request->getPost('current_password');
        $new      = (string) $this->request->getPost('new_password');
        $confirm  = (string) $this->request->getPost('confirm_password');
        $errors   = [];
        $users    = new UserModel();
        $row      = $users->find($me['id']);

        if ($current === '' || ! password_verify($current, (string) $row['password_hash'])) {
            $errors['current_password'] = 'Password saat ini salah.';
            log_activity('password_change_failed', 'Gagal ganti password (password lama salah)');
        }
        if ($policy = password_policy_error($new, (string) $me['username'])) {
            $errors['new_password'] = $policy;
        } elseif ($current !== '' && hash_equals($current, $new)) {
            $errors['new_password'] = 'Password baru harus berbeda dari password saat ini.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Konfirmasi password tidak sama.';
        }

        if ($errors !== []) {
            return redirect()->to(site_url('profile/password'))->with('errors', $errors);
        }

        $users->update($me['id'], [
            'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'password_changed_at'  => date('Y-m-d H:i:s'),
        ]);
        log_activity('password_change', 'Mengganti password');

        // Terbitkan ulang ID sesi setelah perubahan kredensial.
        session()->regenerate(true);

        return $this->flash('profile', 'success', 'Password berhasil diganti.');
    }
}
