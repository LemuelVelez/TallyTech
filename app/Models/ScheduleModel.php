<?php
namespace App\Models;
use CodeIgniter\Model;
class ScheduleModel extends Model
{
    protected $table='schedules'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','sport_id','location_id','round','match_date','team_a_id','team_b_id','status','created_at'];
}
