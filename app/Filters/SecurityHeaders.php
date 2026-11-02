<?php

namespace App\Filters;

use App\Libraries\Captcha;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Header keamanan untuk setiap respons. */
class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest && $request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->hasHeader('Content-Security-Policy')) {
            $response->setHeader('Content-Security-Policy', $this->csp());
        }

        // Halaman HTML (berisi data internal) tidak boleh di-cache browser / proxy.
        $type = $response->getHeaderLine('Content-Type');
        if ($type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/json')) {
            if (! $response->hasHeader('Cache-Control')) {
                $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->setHeader('Pragma', 'no-cache');
            }
        }

        return $response;
    }

    private function csp(): string
    {
        $directives = [
            'default-src'     => ["'self'"],
            'script-src'      => ["'self'"],
            'style-src'       => ["'self'", "'unsafe-inline'"],
            'img-src'         => ["'self'", 'data:'],
            'font-src'        => ["'self'"],
            'connect-src'     => ["'self'"],
            'frame-src'       => ["'none'"],
            'object-src'      => ["'none'"],
            'base-uri'        => ["'self'"],
            'form-action'     => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        foreach ((new Captcha())->cspSources() as $directive => $sources) {
            $directives[$directive] = array_values(array_unique(array_merge(
                array_diff($directives[$directive] ?? [], ["'none'"]),
                $sources
            )));
        }

        $parts = [];
        foreach ($directives as $name => $values) {
            $parts[] = $name . ' ' . implode(' ', $values);
        }

        return implode('; ', $parts);
    }
}
