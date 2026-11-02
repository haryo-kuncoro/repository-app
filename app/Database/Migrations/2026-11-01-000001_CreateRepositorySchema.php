<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRepositorySchema extends Migration
{
    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true];
    }

    public function up(): void
    {
        // roles ------------------------------------------------------------
        $this->forge->addField([
            'id'          => $this->id(),
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('roles', true);

        // permissions ------------------------------------------------------
        $this->forge->addField([
            'id'         => $this->id(),
            'name'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'label'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'group_name' => ['type' => 'VARCHAR', 'constraint' => 60],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('permissions', true);

        // role_permissions -------------------------------------------------
        $this->forge->addField([
            'role_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['role_id', 'permission_id']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions', true);

        // users ------------------------------------------------------------
        $this->forge->addField([
            'id'                   => $this->id(),
            'role_id'              => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'username'             => ['type' => 'VARCHAR', 'constraint' => 30],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 150],
            'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'password_changed_at'  => ['type' => 'DATETIME', 'null' => true],
            'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
            'last_login_ip'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('role_id');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('users', true);

        // login_attempts ---------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'identifier' => ['type' => 'VARCHAR', 'constraint' => 100],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'credential'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['identifier', 'ip_address', 'created_at']);
        $this->forge->addKey(['ip_address', 'created_at']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('login_attempts', true);

        // categories -------------------------------------------------------
        $this->forge->addField([
            'id'          => $this->id(),
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'icon'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'bi-folder'],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('categories', true);

        // files ------------------------------------------------------------
        $this->forge->addField([
            'id'             => $this->id(),
            'category_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'author'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'doc_year'       => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true, 'null' => true],
            'keywords'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'original_name'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'    => ['type' => 'VARCHAR', 'constraint' => 80],
            'extension'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'mime_type'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'file_size'      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'checksum'       => ['type' => 'CHAR', 'constraint' => 64],
            'visibility'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'umum'],
            'uploaded_by'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'download_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('stored_name');
        $this->forge->addKey('category_id');
        $this->forge->addKey('uploaded_by');
        $this->forge->addKey('doc_year');
        $this->forge->addKey('deleted_at');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('files', true);

        // activity_logs ----------------------------------------------------
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'username'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 40],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('action');
        $this->forge->addKey('created_at');
        $this->forge->createTable('activity_logs', true);
    }

    public function down(): void
    {
        foreach (['activity_logs', 'files', 'categories', 'login_attempts', 'users', 'role_permissions', 'permissions', 'roles'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
