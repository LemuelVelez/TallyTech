<?php
namespace App\Models;
use CodeIgniter\Model;
class TeamModel extends Model
{
    protected $table='teams'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['name','code','created_at'];
}
