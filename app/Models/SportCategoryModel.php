<?php

namespace App\Models;

use CodeIgniter\Model;

class SportCategoryModel extends Model
{
    protected $table = 'sport_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'is_active', 'created_at', 'updated_at'];
}
