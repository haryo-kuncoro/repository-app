<?php

namespace App\Libraries;

use Config\Repository;

/**
 * Pembatas percobaan login (anti brute force) berbasis database.
 *
 * Tiga lapis, mana pun yang terlampaui akan mengunci:
 *  1. kombinasi username + IP   (maxAttemptsPerAccount) -> korban tidak bisa dikunci dari IP lain
 *  2. username dari semua IP    (maxAttemptsPerIdentifier) -> serangan terdistribusi
 *  3. satu IP, semua username   (maxAttemptsPerIp)        -> password spraying
 *
 * Percobaan baru TIDAK dicatat selama terkunci, sehingga kunci tidak
 * diperpanjang terus-menerus oleh penyerang.
 */
class Throttle
{
    private Repository $cfg;

    public function __construct(?Repository $cfg = null)
    {
        $this->cfg = $cfg ?? config(Repository::class);
    }

    public static function normalize(string $identifier): string
    {
        return mb_substr(mb_strtolower(trim($identifier)), 0, 100);
    }

    /** Sisa detik penguncian; 0 bila boleh mencoba. */
    public function retryAfter(string $identifier, string $ip): int
    {
        $identifier = self::normalize($identifier);

        return max(
            $this->remaining(['identifier' => $identifier, 'ip_address' => $ip], $this->cfg->maxAttemptsPerAccount, ['credential']),
            $this->remaining(['identifier' => $identifier], $this->cfg->maxAttemptsPerIdentifier, ['credential']),
            $this->remaining(['ip_address' => $ip], $this->cfg->maxAttemptsPerIp, ['credential', 'captcha'])
        );
    }

    /** Catat percobaan gagal. $type: 'credential' | 'captcha'. */
    public function hit(string $identifier, string $ip, string $type = 'credential'): void
    {
        db_connect()->table('login_attempts')->insert([
            'identifier' => self::normalize($identifier),
            'ip_address' => mb_substr($ip, 0, 45),
            'type'       => $type,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (random_int(1, 50) === 1) {
            $this->prune();
        }
    }

    /** Hapus catatan gagal untuk kombinasi ini setelah login berhasil. */
    public function clear(string $identifier, string $ip): void
    {
        db_connect()->table('login_attempts')
            ->where('identifier', self::normalize($identifier))
            ->where('ip_address', $ip)
            ->delete();
    }

    /** Bersihkan catatan lama (> 7 hari). */
    public function prune(): void
    {
        db_connect()->table('login_attempts')
            ->where('created_at <', date('Y-m-d H:i:s', time() - 7 * 86400))
            ->delete();
    }

    /**
     * @param array<string,string> $where
     * @param list<string>         $types
     */
    private function remaining(array $where, int $max, array $types): int
    {
        $window = $this->cfg->lockMinutes * 60;

        $builder = db_connect()->table('login_attempts')
            ->select('created_at')
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $window))
            ->whereIn('type', $types);

        foreach ($where as $column => $value) {
            $builder->where($column, $value);
        }

        $rows = $builder->orderBy('created_at', 'DESC')->limit($max)->get()->getResultArray();
        if (count($rows) < $max) {
            return 0;
        }

        $oldest = strtotime($rows[$max - 1]['created_at']);

        return max(0, $oldest + $window - time());
    }
}
