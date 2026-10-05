<?php
namespace App\Models;
use CodeIgniter\Model;
class ResultModel extends Model
{
    protected $table='results'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','schedule_id','type','status','notes','return_note','returned_by','returned_at','submitted_by','approved_by','validated_by','submitted_at','approved_at','validated_at'];
}
