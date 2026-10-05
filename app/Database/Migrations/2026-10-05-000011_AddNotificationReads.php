<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNotificationReads extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'notification_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'read_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey(['notification_id', 'user_id'], true);
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_reads', true);
    }

    public function down()
    {
        $this->forge->dropTable('notification_reads', true);
    }
}
