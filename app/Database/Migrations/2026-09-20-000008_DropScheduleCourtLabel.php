<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropScheduleCourtLabel extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('court_label', 'schedules')) {
            $this->forge->dropColumn('schedules', 'court_label');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('court_label', 'schedules')) {
            $this->forge->addColumn('schedules', [
                'court_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                    'null' => true,
                    'after' => 'feeds_from_b_type',
                ],
            ]);
        }
    }
}
