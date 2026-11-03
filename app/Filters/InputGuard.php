<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Penjaga tipe input (type confusion).
 *
 * Hampir semua parameter aplikasi ini bertipe teks. Parameter bertipe array
 * (mis. "title[]=x" atau "search[value][]=x") hanya sah di tempat tertentu;
 * di tempat lain ditolak dengan HTTP 400 sebelum mencapai controller, sehingga
 * tidak ada cast array->string yang memicu error / perilaku tak terduga.
 */
class InputGuard implements FilterInterface
{
    /** Kunci POST yang boleh berupa array (daftar ID hak akses). */
    private const POST_ARRAYS = ['permissions'];

    /** Endpoint GET DataTables dan kunci array yang sah. */
    private const GET_ARRAYS = [
        'files/data' => ['search', 'order', 'columns'],
        'logs/data'  => ['search', 'order', 'columns'],
    ];

    private const MAX_DEPTH = 4;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            return null;
        }

        $path    = trim(uri_string(), '/');
        $allowedGet = self::GET_ARRAYS[$path] ?? [];

        if ($this->bad($_GET, $allowedGet) || $this->bad($_POST, self::POST_ARRAYS)) {
            return service('response')->setStatusCode(400)->setBody('Permintaan tidak valid.');
        }

        // Nilai DataTables yang sah selalu skalar di dalam array; tolak array di kedalaman tak wajar.
        foreach ($allowedGet as $key) {
            if (isset($_GET[$key]) && $this->depth($_GET[$key]) > self::MAX_DEPTH) {
                return service('response')->setStatusCode(400)->setBody('Permintaan tidak valid.');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /** @param array<string, mixed> $input @param list<string> $arrayKeys */
    private function bad(array $input, array $arrayKeys): bool
    {
        foreach ($input as $key => $value) {
            if (is_array($value) && ! in_array((string) $key, $arrayKeys, true)) {
                return true;
            }
        }

        return false;
    }

    private function depth(mixed $v): int
    {
        if (! is_array($v)) {
            return 0;
        }
        $max = 0;
        foreach ($v as $item) {
            $max = max($max, $this->depth($item));
        }

        return $max + 1;
    }
}
