<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login. Juga memaksa pengguna yang berstatus "wajib ganti password"
 * menyelesaikan penggantian password sebelum mengakses fitur lain.
 */
class AuthFilter implements FilterInterface
{
    /** URI yang boleh diakses saat wajib ganti password. */
    private const ALLOWED_WHEN_MUST_CHANGE = ['profile/password', 'logout'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'Sesi berakhir. Silakan login kembali.']);
            }

            return redirect()->to(site_url('login'))->with('warning', 'Silakan login terlebih dahulu.');
        }

        $user = $auth->user();
        if ((int) $user['must_change_password'] === 1 && ! in_array(trim(uri_string(), '/'), self::ALLOWED_WHEN_MUST_CHANGE, true)) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['error' => 'Anda wajib mengganti password terlebih dahulu.']);
            }

            return redirect()->to(site_url('profile/password'))->with('warning', 'Demi keamanan, ganti password Anda terlebih dahulu.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
