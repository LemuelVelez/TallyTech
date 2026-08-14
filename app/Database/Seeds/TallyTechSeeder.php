<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TallyTechSeeder extends Seeder
{
    public function run()
    {
        $now=date('Y-m-d H:i:s');
        $users=[
            ['username'=>'admin','password_hash'=>password_hash('admin123',PASSWORD_DEFAULT),'display_name'=>'System Admin','role'=>'admin','status'=>'active','created_at'=>$now],
            ['username'=>'manager','password_hash'=>password_hash('manager123',PASSWORD_DEFAULT),'display_name'=>'Joy Tournament Manager','role'=>'manager','status'=>'active','created_at'=>$now],
            ['username'=>'validator','password_hash'=>password_hash('validator123',PASSWORD_DEFAULT),'display_name'=>'ISF Validator','role'=>'validator','status'=>'active','created_at'=>$now],
            ['username'=>'facilitator','password_hash'=>password_hash('facilitator123',PASSWORD_DEFAULT),'display_name'=>'Game Facilitator','role'=>'facilitator','status'=>'active','created_at'=>$now],
        ];
        $this->db->table('users')->insertBatch($users);
        $ids=[]; foreach($this->db->table('users')->get()->getResultArray() as $u) $ids[$u['username']]=$u['id'];
        $this->db->table('events')->insert(['name'=>'Intercollegiate Students Festival 2026','year'=>2026,'start_date'=>'2026-08-15','end_date'=>'2026-08-18','status'=>'active','is_active'=>1,'created_at'=>$now]);
        $eventId=(int)$this->db->insertID();
        $this->db->table('teams')->insertBatch([
            ['name'=>'CBA Lions','code'=>'CBA','created_at'=>$now],['name'=>'CCS Panthers & CAF Buffaloes','code'=>'CCS-CAF','created_at'=>$now],['name'=>'CIT Dragons & COC Stallions','code'=>'CIT-COC','created_at'=>$now],['name'=>'SCA Eagles & CLAIM Phoenix','code'=>'SCA-CLAIM','created_at'=>$now],
        ]);
        $teams=[]; foreach($this->db->table('teams')->get()->getResultArray() as $t) $teams[$t['code']]=$t['id'];
        $this->db->table('locations')->insertBatch([['name'=>'Main Gymnasium'],['name'=>'Covered Court'],['name'=>'ISF Field'],['name'=>'Auditorium']]);
        $locs=[]; foreach($this->db->table('locations')->get()->getResultArray() as $l) $locs[$l['name']]=$l['id'];
        $sports=[
            ['event_id'=>$eventId,'name'=>'Basketball','category'=>'Men','result_type'=>'match','created_at'=>$now],
            ['event_id'=>$eventId,'name'=>'Volleyball','category'=>'Women','result_type'=>'match','created_at'=>$now],
            ['event_id'=>$eventId,'name'=>'Badminton','category'=>'Men','result_type'=>'match','created_at'=>$now],
            ['event_id'=>$eventId,'name'=>'Cheerdance','category'=>'Mixed','result_type'=>'judged','created_at'=>$now],
        ];
        $this->db->table('sports')->insertBatch($sports);
        $sportRows=$this->db->table('sports')->get()->getResultArray(); $sport=[]; foreach($sportRows as $s)$sport[$s['name']]=$s['id'];
        foreach([$sport['Basketball'],$sport['Volleyball'],$sport['Badminton'],$sport['Cheerdance']] as $sid) $this->db->table('user_sports')->insert(['user_id'=>$ids['manager'],'sport_id'=>$sid]);
        foreach([$sport['Basketball'],$sport['Volleyball'],$sport['Cheerdance']] as $sid) $this->db->table('user_sports')->insert(['user_id'=>$ids['facilitator'],'sport_id'=>$sid]);
        foreach($sport as $sid) $this->db->table('weighted_points')->insert(['event_id'=>$eventId,'sport_id'=>$sid,'first_points'=>10,'second_points'=>7,'third_points'=>5,'participation_points'=>2,'status'=>'validated','submitted_by'=>$ids['manager'],'validated_by'=>$ids['validator'],'submitted_at'=>$now,'validated_at'=>$now]);
        $schedules=[
            ['event_id'=>$eventId,'sport_id'=>$sport['Basketball'],'location_id'=>$locs['Main Gymnasium'],'round'=>'Final','match_date'=>'2026-08-15 18:00:00','team_a_id'=>$teams['CBA'],'team_b_id'=>$teams['CCS-CAF'],'status'=>'played','created_at'=>$now],
            ['event_id'=>$eventId,'sport_id'=>$sport['Volleyball'],'location_id'=>$locs['Covered Court'],'round'=>'Final','match_date'=>'2026-08-16 09:00:00','team_a_id'=>$teams['CIT-COC'],'team_b_id'=>$teams['SCA-CLAIM'],'status'=>'scheduled','created_at'=>$now],
            ['event_id'=>$eventId,'sport_id'=>$sport['Cheerdance'],'location_id'=>$locs['Auditorium'],'round'=>'Championship','match_date'=>'2026-08-16 14:00:00','team_a_id'=>null,'team_b_id'=>null,'status'=>'played','created_at'=>$now],
        ];
        $this->db->table('schedules')->insertBatch($schedules);
        $sch=$this->db->table('schedules')->orderBy('id')->get()->getResultArray();
        $this->db->table('results')->insert(['event_id'=>$eventId,'schedule_id'=>$sch[0]['id'],'type'=>'match','status'=>'validated','submitted_by'=>$ids['facilitator'],'validated_by'=>$ids['validator'],'submitted_at'=>$now,'validated_at'=>$now]);
        $r1=(int)$this->db->insertID();
        $this->db->table('result_entries')->insertBatch([['result_id'=>$r1,'team_id'=>$teams['CBA'],'raw_score'=>88,'placement'=>1,'allocated_points'=>10],['result_id'=>$r1,'team_id'=>$teams['CCS-CAF'],'raw_score'=>81,'placement'=>2,'allocated_points'=>7]]);
        $this->db->table('results')->insert(['event_id'=>$eventId,'schedule_id'=>$sch[2]['id'],'type'=>'judged','status'=>'pending','submitted_by'=>$ids['facilitator'],'submitted_at'=>$now]);
        $r2=(int)$this->db->insertID();
        $this->db->table('result_entries')->insertBatch([
            ['result_id'=>$r2,'team_id'=>$teams['SCA-CLAIM'],'raw_score'=>94.5,'placement'=>1,'allocated_points'=>0],['result_id'=>$r2,'team_id'=>$teams['CIT-COC'],'raw_score'=>92,'placement'=>2,'allocated_points'=>0],['result_id'=>$r2,'team_id'=>$teams['CBA'],'raw_score'=>89.5,'placement'=>3,'allocated_points'=>0],['result_id'=>$r2,'team_id'=>$teams['CCS-CAF'],'raw_score'=>87,'placement'=>4,'allocated_points'=>0],
        ]);
        $this->db->table('notifications')->insertBatch([
            ['actor_user_id'=>$ids['facilitator'],'action'=>'result_submitted','message'=>'Submitted unofficial Cheerdance judged result','created_at'=>$now],
            ['actor_user_id'=>$ids['validator'],'action'=>'result_validated','message'=>'Validated Basketball final as official','created_at'=>$now],
            ['actor_user_id'=>$ids['manager'],'action'=>'weighted_points_validated','message'=>'Weighted points are ready for scoring','created_at'=>$now],
        ]);
    }
}
