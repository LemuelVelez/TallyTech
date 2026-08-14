<?php
namespace App\Models;
use CodeIgniter\Model;
class ResultModel extends Model
{
    protected $table='results'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','schedule_id','type','status','notes','submitted_by','validated_by','submitted_at','validated_at'];
}
