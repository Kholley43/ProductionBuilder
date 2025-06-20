<?php

namespace App\Models;

use CodeIgniter\Model;

class BlogPost extends Model
{
    protected $table            = 'blogposts';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['title', 'content', 'author', 'published_date'];
    protected $useTimestamps    = true;
}