<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBracketScheduling extends Migration
{
    public function up()
    {
        $this->forge->addColumn('schedules', [
            'match_code' => ['type'=>'VARCHAR','constraint'=>10,'null'=>true],
            'phase' => ['type'=>'ENUM','constraint'=>['playoff','lower_r1','quarter','semi','final','tiebreaker'],'null'=>true],
            'bracket_side' => ['type'=>'ENUM','constraint'=>['upper','lower','grand'],'null'=>true],
            'bracket_order' => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'default'=>0],
            'feeds_from_a' => ['type'=>'VARCHAR','constraint'=>10,'null'=>true],
            'feeds_from_a_type' => ['type'=>'ENUM','constraint'=>['winner','loser'],'null'=>true],
            'feeds_from_b' => ['type'=>'VARCHAR','constraint'=>10,'null'=>true],
            'feeds_from_b_type' => ['type'=>'ENUM','constraint'=>['winner','loser'],'null'=>true],
            'court_label' => ['type'=>'VARCHAR','constraint'=>60,'null'=>true],
            'is_conditional' => ['type'=>'TINYINT','constraint'=>1,'default'=>0],
            'scheduling_note' => ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('schedules', ['match_code','phase','bracket_side','bracket_order','feeds_from_a','feeds_from_a_type','feeds_from_b','feeds_from_b_type','court_label','is_conditional','scheduling_note']);
    }
}
