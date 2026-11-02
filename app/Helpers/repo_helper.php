<?php

use App\Libraries\Auth;

if (! function_exists('auth')) {
    function auth(): Auth
    {
        return service('auth');
    }
}

if (! function_exists('current_user')) {
    /** Data pengguna yang sedang login (atau null). */
    function current_user(): ?array
    {
        return service('auth')->user();
    }
}

if (! function_exists('can')) {
    /** True bila pengguna memiliki SALAH SATU permission yang diberikan. */
    function can(string ...$permissions): bool
    {
        return service('auth')->can(...$permissions);
    }
}

if (! function_exists('client_ip')) {
    function client_ip(): string
    {
        return service('request')->getIPAddress();
    }
}

if (! function_exists('log_activity')) {
    /**
     * Catat aktivitas ke tabel activity_logs. Tidak pernah melempar error
     * agar kegagalan log tidak menggagalkan proses utama.
     */
    function log_activity(string $action, string $description, ?array $user = null): void
    {
        try {
            $user ??= service('auth')->user();
            $request = service('request');
            $agent   = method_exists($request, 'getUserAgent') ? (string) $request->getUserAgent()->getAgentString() : '';

            db_connect()->table('activity_logs')->insert([
                'user_id'     => $user['id'] ?? null,
                'username'    => isset($user['username']) ? mb_substr((string) $user['username'], 0, 100) : null,
                'action'      => mb_substr($action, 0, 40),
                'description' => mb_substr($description, 0, 255),
                'ip_address'  => mb_substr((string) $request->getIPAddress(), 0, 45),
                'user_agent'  => mb_substr($agent, 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Gagal mencatat aktivitas: ' . $e->getMessage());
        }
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(int|string|null $bytes, int $precision = 1): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $i     = min($i, count($units) - 1);

        return ($i === 0 ? (string) (int) $bytes : number_format($bytes / (1024 ** $i), $precision, ',', '.')) . ' ' . $units[$i];
    }
}

if (! function_exists('file_icon')) {
    /** Kelas Bootstrap Icons + warna untuk ekstensi tertentu. */
    function file_icon(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf'                => 'bi-file-earmark-pdf text-danger',
            'doc', 'docx', 'odt' => 'bi-file-earmark-word text-primary',
            'xls', 'xlsx', 'ods', 'csv' => 'bi-file-earmark-excel text-success',
            'ppt', 'pptx', 'odp' => 'bi-file-earmark-slides text-warning',
            'zip'                => 'bi-file-earmark-zip text-secondary',
            'jpg', 'jpeg', 'png' => 'bi-file-earmark-image text-info',
            'txt'                => 'bi-file-earmark-text text-secondary',
            default              => 'bi-file-earmark text-secondary',
        };
    }
}

if (! function_exists('password_policy_error')) {
    /**
     * Validasi kekuatan password. Mengembalikan pesan error atau null bila lolos.
     */
    function password_policy_error(string $password, string $username = ''): ?string
    {
        $min = (int) config('Repository')->passwordMinLength;

        if (mb_strlen($password) < $min) {
            return "Password minimal {$min} karakter.";
        }
        if (strlen($password) > 72) {
            return 'Password maksimal 72 karakter.';
        }
        if (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password)) {
            return 'Password harus mengandung huruf kecil, huruf besar, dan angka.';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            return 'Password tidak boleh mengandung username.';
        }
        $weak = ['password', 'passw0rd', '12345678', 'qwerty', 'admin', 'repository', 'letmein'];
        foreach ($weak as $w) {
            if (stripos($password, $w) !== false) {
                return 'Password terlalu mudah ditebak. Hindari kata umum seperti "password" atau "admin".';
            }
        }

        return null;
    }
}

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $out   = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}

if (! function_exists('asset_url')) {
    /** URL aset publik dengan cache-busting berdasarkan waktu modifikasi file. */
    function asset_url(string $path): string
    {
        $file    = FCPATH . ltrim($path, '/');
        $version = is_file($file) ? '?v=' . filemtime($file) : '';

        return base_url('assets/' . ltrim($path, '/')) . $version;
    }
}

if (! function_exists('old_value')) {
    /** Nilai input sebelumnya (flash 'old') untuk mengisi ulang form setelah error validasi. */
    function old_value(string $key, mixed $default = ''): mixed
    {
        $old = session()->getFlashdata('old');

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (! function_exists('form_errors')) {
    /** @return array<string,string> */
    function form_errors(): array
    {
        $errors = session()->getFlashdata('errors');

        return is_array($errors) ? $errors : [];
    }
}

if (! function_exists('has_error')) {
    function has_error(string $field): string
    {
        return isset(form_errors()[$field]) ? ' is-invalid' : '';
    }
}

if (! function_exists('field_error')) {
    function field_error(string $field): string
    {
        $errors = form_errors();

        return isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
    }
}
