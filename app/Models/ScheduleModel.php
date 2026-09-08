<?php
namespace App\Models;
use CodeIgniter\Model;
class ScheduleModel extends Model
{
    protected $table='schedules'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','sport_id','location_id','round','tournament_format','match_date','team_a_id','team_b_id','status','created_at','match_code','phase','bracket_side','bracket_order','feeds_from_a','feeds_from_a_type','feeds_from_b','feeds_from_b_type','court_label','is_conditional','scheduling_note'];
}
