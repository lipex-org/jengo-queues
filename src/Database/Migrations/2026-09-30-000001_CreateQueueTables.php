<?php

declare(strict_types=1);

namespace Jengo\Queues\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQueueTables extends Migration
{
    public function up(): void
    {
        // Jobs table
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'queue' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'default'    => 'default',
            ],
            'payload' => [
                'type' => 'LONGTEXT',
            ],
            'attempts' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'reserved_at' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'available_at' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['queue', 'reserved_at', 'available_at']);
        $this->forge->createTable('queue_jobs', true);

        // Failed jobs table
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'connection' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'queue' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'payload' => [
                'type' => 'LONGTEXT',
            ],
            'exception' => [
                'type' => 'LONGTEXT',
            ],
            'failed_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['connection', 'queue']);
        $this->forge->createTable('queue_failed_jobs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('queue_jobs', true);
        $this->forge->dropTable('queue_failed_jobs', true);
    }
}
