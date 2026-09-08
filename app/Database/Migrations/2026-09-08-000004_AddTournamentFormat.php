<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTournamentFormat extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('tournament_format', 'schedules')) {
            $this->forge->addColumn('schedules', [
                'tournament_format' => [
                    'type' => 'ENUM',
                    'constraint' => ['single_elimination', 'double_elimination'],
                    'default' => 'single_elimination',
                    'after' => 'round',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('tournament_format', 'schedules')) {
            $this->forge->dropColumn('schedules', 'tournament_format');
        }
    }
}
