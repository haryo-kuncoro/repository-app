<?php

namespace App\Controllers;

class Dashboard extends BaseController
{
    public function index()
    {
        $auth      = service('auth');
        $db        = db_connect();
        $user      = $this->me();
        $manageAll = $auth->can('files.manage_all');

        // Berkas yang boleh dilihat pengguna ini.
        $visible = function () use ($db, $user, $manageAll) {
            $b = $db->table('files f')->where('f.deleted_at', null);
            if (! $manageAll) {
                $b->groupStart()->where('f.visibility', 'umum')->orWhere('f.uploaded_by', (int) $user['id'])->groupEnd();
            }

            return $b;
        };

        $totals = $visible()->select('COUNT(*) AS n, COALESCE(SUM(f.file_size),0) AS bytes, COALESCE(SUM(f.download_count),0) AS downloads')->get()->getRowArray();

        $perCategory = $db->table('categories c')
            ->select('c.id, c.name, c.icon')
            ->where('c.is_active', 1)->orderBy('c.name')->get()->getResultArray();

        $counts = $visible()->select('f.category_id, COUNT(*) AS n')->groupBy('f.category_id')->get()->getResultArray();
        $counts = array_column($counts, 'n', 'category_id');
        foreach ($perCategory as &$c) {
            $c['n'] = (int) ($counts[$c['id']] ?? 0);
        }
        unset($c);

        $recent = $visible()
            ->select('f.id, f.title, f.extension, f.file_size, f.created_at, c.name AS category_name, u.name AS uploader_name')
            ->join('categories c', 'c.id = f.category_id', 'left')
            ->join('users u', 'u.id = f.uploaded_by', 'left')
            ->orderBy('f.created_at', 'DESC')->orderBy('f.id', 'DESC')->limit(8)->get()->getResultArray();

        $logs = [];
        if ($auth->can('logs.view')) {
            $logs = $db->table('activity_logs')->orderBy('id', 'DESC')->limit(8)->get()->getResultArray();
        }

        return view('dashboard/index', [
            'totals'      => $totals,
            'perCategory' => $perCategory,
            'recent'      => $recent,
            'logs'        => $logs,
            'userCount'   => $auth->can('users.manage') ? $db->table('users')->countAllResults() : null,
            'catCount'    => $db->table('categories')->where('is_active', 1)->countAllResults(),
        ]);
    }
}
