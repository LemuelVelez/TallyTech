<?php
namespace App\Models;
use CodeIgniter\Model;
class EventModel extends Model
{
    protected $table='events'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['name','year','start_date','end_date','status','is_active','created_at'];
}
