<?php
require __DIR__.'/app/Config/Paths.php';
require __DIR__.'/vendor/codeigniter4/framework/system/Boot.php';
$paths = new \Config\Paths();
\CodeIgniter\Boot::bootSpark($paths); 