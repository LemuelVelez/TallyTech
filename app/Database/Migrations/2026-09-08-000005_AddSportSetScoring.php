<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSportSetScoring extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('set_count', 'sports')) {
            $this->forge->addColumn('sports', [
                'set_count' => [
                    'type' => 'TINYINT',
                    'unsigned' => true,
                    'default' => 3,
                    'after' => 'result_type',
                ],
            ]);
        }

        if (! $this->db->fieldExists('winning_points', 'sports')) {
            $this->forge->addColumn('sports', [
                'winning_points' => [
                    'type' => 'DECIMAL',
                    'constraint' => '8,2',
                    'null' => true,
                    'after' => 'set_count',
                ],
            ]);
        }

        if (! $this->db->fieldExists('set_scores', 'result_entries')) {
            $this->forge->addColumn('result_entries', [
                'set_scores' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'raw_score',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('set_scores', 'result_entries')) {
            $this->forge->dropColumn('result_entries', 'set_scores');
        }

        if ($this->db->fieldExists('winning_points', 'sports')) {
            $this->forge->dropColumn('sports', 'winning_points');
        }

        if ($this->db->fieldExists('set_count', 'sports')) {
            $this->forge->dropColumn('sports', 'set_count');
        }
    }
}
