<?php
namespace App\Models;
use CodeIgniter\Model;
class NotificationModel extends Model
{
    protected $table='notifications'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['actor_user_id','action','message','created_at'];
}
