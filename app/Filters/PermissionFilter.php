<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Otorisasi RBAC. Pemakaian pada route:  ['filter' => 'perm:files.view']
 * Beberapa permission dipisah koma = cukup salah satu:  'perm:files.edit,files.manage_all'
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            return redirect()->to(site_url('login'));
        }

        $required = array_values(array_filter(array_map('trim', (array) $arguments)));
        // Tanpa argumen = tolak (gagal tertutup).
        if ($required === [] || ! $auth->can(...$required)) {
            log_activity('forbidden', 'Akses ditolak ke /' . trim(uri_string(), '/'));

            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['error' => 'Anda tidak memiliki izin untuk aksi ini.']);
            }

            return service('response')->setStatusCode(403)->setBody(view('errors/forbidden'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
