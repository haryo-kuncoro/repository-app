<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;

class Logs extends BaseController
{
    public function index()
    {
        return view('logs/index', ['actionList' => (new ActivityLogModel())->actions()]);
    }

    public function data()
    {
        return $this->response->setJSON((new ActivityLogModel())->datatable($this->request->getGet()));
    }
}
