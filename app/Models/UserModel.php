<?php
namespace App\Models;
use CodeIgniter\Model;
class UserModel extends Model
{
    protected $table='users'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['username','password_hash','display_name','role','status','created_at'];
}
