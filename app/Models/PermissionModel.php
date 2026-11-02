<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table         = 'permissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['name', 'label', 'group_name'];

    /** @return array<string, list<array<string,mixed>>> dikelompokkan per grup */
    public function grouped(): array
    {
        $out = [];
        foreach ($this->orderBy('group_name', 'ASC')->orderBy('id', 'ASC')->findAll() as $row) {
            $out[$row['group_name']][] = $row;
        }

        return $out;
    }
}
