<?php
namespace App\Models;
use CodeIgniter\Model;
class SportModel extends Model
{
    protected $table='sports'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['event_id','name','category','result_type','set_count','winning_points','created_at'];
}
