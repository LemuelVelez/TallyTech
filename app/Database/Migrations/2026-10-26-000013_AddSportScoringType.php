<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddSportScoringType extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('scoring_type', 'sports')) {
            $this->forge->addColumn('sports', [
                'scoring_type' => ['type'=>'ENUM','constraint'=>['points','win_loss'],'default'=>'points','null'=>false,'after'=>'result_type'],
            ]);
        }
    }
    public function down()
    {
        if ($this->db->fieldExists('scoring_type', 'sports')) $this->forge->dropColumn('sports','scoring_type');
    }
}
