<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'username', 'action', 'description', 'ip_address', 'user_agent', 'created_at'];

    private const ORDERABLE = [
        0 => 'l.created_at',
        1 => 'l.username',
        2 => 'l.action',
        3 => 'l.description',
        4 => 'l.ip_address',
    ];

    /** @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string,mixed>>} */
    public function datatable(array $q): array
    {
        $total = $this->db->table('activity_logs')->countAllResults();

        $apply = static function ($b) use ($q): void {
            $search = trim(arr_str($q, 'search.value'));
            if ($search !== '') {
                $b->groupStart()->like('l.username', $search)->orLike('l.description', $search)
                    ->orLike('l.ip_address', $search)->orLike('l.action', $search)->groupEnd();
            }
            if (($action = trim(arr_str($q, 'action'))) !== '') {
                $b->where('l.action', $action);
            }
        };

        $fb = $this->db->table('activity_logs l');
        $apply($fb);
        $filtered = $fb->countAllResults();

        $db = $this->db->table('activity_logs l')->select('l.id, l.username, l.action, l.description, l.ip_address, l.created_at');
        $apply($db);

        $col = (int) arr_str($q, 'order.0.column', '0');
        $dir = strtolower(arr_str($q, 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $db->orderBy(self::ORDERABLE[$col] ?? 'l.created_at', $dir)->orderBy('l.id', 'DESC');

        $length = (int) arr_str($q, 'length', '25');
        $length = ($length < 1 || $length > 100) ? 25 : $length;

        return [
            'draw'            => (int) arr_str($q, 'draw', '0'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $db->limit($length, max(0, (int) arr_str($q, 'start', '0')))->get()->getResultArray(),
        ];
    }

    public function actions(): array
    {
        return array_column($this->db->table('activity_logs')->select('action')->distinct()->orderBy('action')->get()->getResultArray(), 'action');
    }
}
