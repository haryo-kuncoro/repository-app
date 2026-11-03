<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScanColumnsToFiles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('files', [
            'scan_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unscanned', 'after' => 'checksum'],
            'scan_engine' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'scan_status'],
            'scanned_at'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'scan_engine'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('files', ['scan_status', 'scan_engine', 'scanned_at']);
    }
}
