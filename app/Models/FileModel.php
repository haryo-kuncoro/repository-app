<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class FileModel extends Model
{
    protected $table            = 'files';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $deletedField     = 'deleted_at';
    protected $allowedFields    = [
        'category_id', 'title', 'description', 'author', 'doc_year', 'keywords',
        'original_name', 'stored_name', 'extension', 'mime_type', 'file_size', 'checksum',
        'visibility', 'uploaded_by', 'download_count', 'scan_status', 'scan_engine', 'scanned_at',
    ];

    /** Kolom yang boleh dipakai untuk pengurutan (indeks kolom DataTables => kolom SQL). */
    private const ORDERABLE = [
        1 => 'f.title',
        2 => 'c.name',
        3 => 'f.author',
        4 => 'f.doc_year',
        5 => 'f.file_size',
        6 => 'f.created_at',
    ];

    /** Query dasar dengan aturan visibilitas. */
    private function base(int $userId, bool $manageAll, bool $trashed = false): BaseBuilder
    {
        $b = $this->db->table('files f')
            ->select('f.id, f.title, f.author, f.doc_year, f.original_name, f.extension, f.file_size, f.visibility, f.uploaded_by, f.created_at, f.deleted_at, c.name AS category_name, u.name AS uploader_name')
            ->join('categories c', 'c.id = f.category_id', 'left')
            ->join('users u', 'u.id = f.uploaded_by', 'left');

        $trashed ? $b->where('f.deleted_at IS NOT NULL', null, false) : $b->where('f.deleted_at', null);

        if (! $manageAll) {
            $b->groupStart()->where('f.visibility', 'umum')->orWhere('f.uploaded_by', $userId)->groupEnd();
        }

        return $b;
    }

    /**
     * Data untuk DataTables server-side.
     *
     * @param array<string,mixed> $q GET params
     *
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string,mixed>>}
     */
    public function datatable(array $q, int $userId, bool $manageAll): array
    {
        $total = $this->base($userId, $manageAll)->countAllResults();

        $apply = function (BaseBuilder $b) use ($q): void {
            $search = trim(arr_str($q, 'search.value'));
            if ($search !== '') {
                $b->groupStart()
                    ->like('f.title', $search)
                    ->orLike('f.author', $search)
                    ->orLike('f.keywords', $search)
                    ->orLike('f.original_name', $search)
                    ->orLike('c.name', $search)
                    ->groupEnd();
            }
            if (($cat = (int) arr_str($q, 'category_id', '0')) > 0) {
                $b->where('f.category_id', $cat);
            }
            if (($year = (int) arr_str($q, 'year', '0')) > 0) {
                $b->where('f.doc_year', $year);
            }
            if (in_array(arr_str($q, 'visibility'), ['umum', 'terbatas'], true)) {
                $b->where('f.visibility', arr_str($q, 'visibility'));
            }
        };

        $filteredBuilder = $this->base($userId, $manageAll);
        $apply($filteredBuilder);
        $filtered = $filteredBuilder->countAllResults();

        $dataBuilder = $this->base($userId, $manageAll);
        $apply($dataBuilder);

        $orderCol = (int) arr_str($q, 'order.0.column', '6');
        $orderDir = strtolower(arr_str($q, 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $dataBuilder->orderBy(self::ORDERABLE[$orderCol] ?? 'f.created_at', $orderDir)->orderBy('f.id', 'DESC');

        $length = (int) arr_str($q, 'length', '10');
        $length = ($length < 1 || $length > 100) ? 10 : $length;
        $start  = max(0, (int) arr_str($q, 'start', '0'));

        $rows = $dataBuilder->limit($length, $start)->get()->getResultArray();

        return [
            'draw'            => (int) arr_str($q, 'draw', '0'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows,
        ];
    }

    /** Detail satu berkas + nama kategori & pengunggah (termasuk yang di tempat sampah bila $withTrashed). */
    public function detail(int $id, bool $withTrashed = false): ?array
    {
        $b = $this->db->table('files f')
            ->select('f.*, c.name AS category_name, u.name AS uploader_name, u.username AS uploader_username')
            ->join('categories c', 'c.id = f.category_id', 'left')
            ->join('users u', 'u.id = f.uploaded_by', 'left')
            ->where('f.id', $id);

        if (! $withTrashed) {
            $b->where('f.deleted_at', null);
        }

        return $b->get()->getRowArray();
    }

    /** Daftar berkas di tempat sampah. */
    public function trashed(): array
    {
        return $this->base(0, true, true)->orderBy('f.deleted_at', 'DESC')->limit(1000)->get()->getResultArray();
    }

    /** Daftar tahun yang tersedia (untuk filter). */
    public function years(int $userId, bool $manageAll): array
    {
        $b = $this->db->table('files f')->select('f.doc_year')->distinct()
            ->where('f.deleted_at', null)->where('f.doc_year IS NOT NULL', null, false);
        if (! $manageAll) {
            $b->groupStart()->where('f.visibility', 'umum')->orWhere('f.uploaded_by', $userId)->groupEnd();
        }

        return array_map('intval', array_column($b->orderBy('f.doc_year', 'DESC')->get()->getResultArray(), 'doc_year'));
    }
}
