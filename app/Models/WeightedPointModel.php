<?php
namespace App\Models;
use CodeIgniter\Model;
class WeightedPointModel extends Model
{
    protected $table='weighted_points'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','sport_id','first_points','second_points','third_points','participation_points','status','submitted_by','validated_by','submitted_at','validated_at'];
}
