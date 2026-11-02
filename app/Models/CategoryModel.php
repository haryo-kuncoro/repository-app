<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table         = 'categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'slug', 'description', 'icon', 'is_active'];

    public function listing(): array
    {
        return $this->db->table('categories c')
            ->select('c.*, (SELECT COUNT(*) FROM files f WHERE f.category_id = c.id AND f.deleted_at IS NULL) AS file_count')
            ->orderBy('c.name', 'ASC')
            ->get()->getResultArray();
    }

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /** Slug unik berdasarkan nama. */
    public function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = url_title($name, '-', true) ?: 'kategori';
        $slug = mb_substr($base, 0, 110);
        $i    = 2;
        while (true) {
            $b = $this->db->table('categories')->where('slug', $slug);
            if ($exceptId !== null) {
                $b->where('id !=', $exceptId);
            }
            if ($b->countAllResults() === 0) {
                return $slug;
            }
            $slug = mb_substr($base, 0, 105) . '-' . $i++;
        }
    }
}
