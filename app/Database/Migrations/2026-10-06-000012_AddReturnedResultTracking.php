<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReturnedResultTracking extends Migration
{
    public function up()
    {
        $this->forge->addColumn('results', [
            'returned_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'return_note'],
            'returned_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'returned_by'],
        ]);
        $this->db->query('ALTER TABLE results ADD CONSTRAINT fk_results_returned_by FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down()
    {
        try { $this->forge->dropForeignKey('results', 'fk_results_returned_by'); } catch (\Throwable $e) { /* rollback stays tolerant */ }
        foreach (['returned_at', 'returned_by'] as $column) {
            if ($this->db->fieldExists($column, 'results')) $this->forge->dropColumn('results', $column);
        }
    }
}
