<?php
namespace App\Models;
use CodeIgniter\Model;
class UserModel extends Model
{
    protected $table='users'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['username','password_hash','generated_password','display_name','role','status','created_by','created_at'];
}
