<?php

namespace App\Models;

use CodeIgniter\Model;

class ACustomer extends Model
{
    protected $table            = 'acustomers';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['name', 'email', 'phone', 'address'];
    protected $useTimestamps    = true;
}