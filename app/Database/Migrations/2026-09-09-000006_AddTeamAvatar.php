<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTeamAvatar extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('avatar_path', 'teams')) {
            $this->forge->addColumn('teams', [
                'avatar_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'code',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('avatar_path', 'teams')) {
            $this->forge->dropColumn('teams', 'avatar_path');
        }
    }
}
