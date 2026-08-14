<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTallyTechSchema extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'username'=>['type'=>'VARCHAR','constraint'=>80],
            'password_hash'=>['type'=>'VARCHAR','constraint'=>255],
            'display_name'=>['type'=>'VARCHAR','constraint'=>120],
            'role'=>['type'=>'ENUM','constraint'=>['admin','manager','validator','facilitator']],
            'status'=>['type'=>'ENUM','constraint'=>['active','inactive'],'default'=>'active'],
            'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey('username'); $this->forge->createTable('users',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'name'=>['type'=>'VARCHAR','constraint'=>150], 'year'=>['type'=>'INT'],
            'start_date'=>['type'=>'DATE','null'=>true], 'end_date'=>['type'=>'DATE','null'=>true],
            'status'=>['type'=>'ENUM','constraint'=>['draft','active','completed'],'default'=>'active'],
            'is_active'=>['type'=>'TINYINT','constraint'=>1,'default'=>0], 'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey(['name','year']); $this->forge->createTable('events',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'name'=>['type'=>'VARCHAR','constraint'=>150],
            'code'=>['type'=>'VARCHAR','constraint'=>30], 'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey('name'); $this->forge->addUniqueKey('code'); $this->forge->createTable('teams',true);

        $this->forge->addField(['id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true],'name'=>['type'=>'VARCHAR','constraint'=>150]]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey('name'); $this->forge->createTable('locations',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'event_id'=>['type'=>'INT','unsigned'=>true],
            'name'=>['type'=>'VARCHAR','constraint'=>120], 'category'=>['type'=>'ENUM','constraint'=>['Men','Women','Mixed']],
            'result_type'=>['type'=>'ENUM','constraint'=>['match','judged'],'default'=>'match'], 'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addForeignKey('event_id','events','id','CASCADE','CASCADE'); $this->forge->addUniqueKey(['event_id','name','category']); $this->forge->createTable('sports',true);

        $this->forge->addField(['user_id'=>['type'=>'INT','unsigned'=>true],'sport_id'=>['type'=>'INT','unsigned'=>true]]);
        $this->forge->addKey(['user_id','sport_id'],true); $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE'); $this->forge->addForeignKey('sport_id','sports','id','CASCADE','CASCADE'); $this->forge->createTable('user_sports',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'event_id'=>['type'=>'INT','unsigned'=>true], 'sport_id'=>['type'=>'INT','unsigned'=>true],
            'location_id'=>['type'=>'INT','unsigned'=>true,'null'=>true], 'round'=>['type'=>'VARCHAR','constraint'=>80,'default'=>'Elimination'],
            'match_date'=>['type'=>'DATETIME'], 'team_a_id'=>['type'=>'INT','unsigned'=>true,'null'=>true], 'team_b_id'=>['type'=>'INT','unsigned'=>true,'null'=>true],
            'status'=>['type'=>'ENUM','constraint'=>['scheduled','played','cancelled'],'default'=>'scheduled'], 'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addForeignKey('event_id','events','id','CASCADE','CASCADE'); $this->forge->addForeignKey('sport_id','sports','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('location_id','locations','id','SET NULL','CASCADE'); $this->forge->addForeignKey('team_a_id','teams','id','SET NULL','CASCADE'); $this->forge->addForeignKey('team_b_id','teams','id','SET NULL','CASCADE'); $this->forge->createTable('schedules',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'event_id'=>['type'=>'INT','unsigned'=>true], 'sport_id'=>['type'=>'INT','unsigned'=>true],
            'first_points'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0], 'second_points'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0], 'third_points'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0], 'participation_points'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0],
            'status'=>['type'=>'ENUM','constraint'=>['pending','validated'],'default'=>'pending'], 'submitted_by'=>['type'=>'INT','unsigned'=>true,'null'=>true], 'validated_by'=>['type'=>'INT','unsigned'=>true,'null'=>true],
            'submitted_at'=>['type'=>'DATETIME','null'=>true], 'validated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey(['event_id','sport_id']); $this->forge->addForeignKey('event_id','events','id','CASCADE','CASCADE'); $this->forge->addForeignKey('sport_id','sports','id','CASCADE','CASCADE'); $this->forge->addForeignKey('submitted_by','users','id','SET NULL','CASCADE'); $this->forge->addForeignKey('validated_by','users','id','SET NULL','CASCADE'); $this->forge->createTable('weighted_points',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'event_id'=>['type'=>'INT','unsigned'=>true], 'schedule_id'=>['type'=>'INT','unsigned'=>true],
            'type'=>['type'=>'ENUM','constraint'=>['match','judged']], 'status'=>['type'=>'ENUM','constraint'=>['pending','validated'],'default'=>'pending'], 'notes'=>['type'=>'TEXT','null'=>true],
            'submitted_by'=>['type'=>'INT','unsigned'=>true,'null'=>true], 'validated_by'=>['type'=>'INT','unsigned'=>true,'null'=>true], 'submitted_at'=>['type'=>'DATETIME','null'=>true], 'validated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addForeignKey('event_id','events','id','CASCADE','CASCADE'); $this->forge->addForeignKey('schedule_id','schedules','id','CASCADE','CASCADE'); $this->forge->addForeignKey('submitted_by','users','id','SET NULL','CASCADE'); $this->forge->addForeignKey('validated_by','users','id','SET NULL','CASCADE'); $this->forge->createTable('results',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'result_id'=>['type'=>'INT','unsigned'=>true], 'team_id'=>['type'=>'INT','unsigned'=>true],
            'raw_score'=>['type'=>'DECIMAL','constraint'=>'10,2','default'=>0], 'placement'=>['type'=>'INT','null'=>true], 'allocated_points'=>['type'=>'DECIMAL','constraint'=>'8,2','default'=>0],
        ]);
        $this->forge->addKey('id',true); $this->forge->addForeignKey('result_id','results','id','CASCADE','CASCADE'); $this->forge->addForeignKey('team_id','teams','id','CASCADE','CASCADE'); $this->forge->createTable('result_entries',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'actor_user_id'=>['type'=>'INT','unsigned'=>true,'null'=>true],
            'action'=>['type'=>'VARCHAR','constraint'=>80], 'message'=>['type'=>'VARCHAR','constraint'=>255], 'created_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addForeignKey('actor_user_id','users','id','SET NULL','CASCADE'); $this->forge->createTable('notifications',true);

        $this->forge->addField([
            'id'=>['type'=>'INT','unsigned'=>true,'auto_increment'=>true], 'user_id'=>['type'=>'INT','unsigned'=>true], 'setting_key'=>['type'=>'VARCHAR','constraint'=>80], 'setting_value'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
        ]);
        $this->forge->addKey('id',true); $this->forge->addUniqueKey(['user_id','setting_key']); $this->forge->addForeignKey('user_id','users','id','CASCADE','CASCADE'); $this->forge->createTable('user_settings',true);
    }

    public function down()
    {
        foreach (['user_settings','notifications','result_entries','results','weighted_points','schedules','user_sports','sports','locations','teams','events','users'] as $table) $this->forge->dropTable($table,true);
    }
}
