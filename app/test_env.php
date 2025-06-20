<?php
require __DIR__.'/vendor/codeigniter4/framework/system/Config/DotEnv.php';

use CodeIgniter\Config\DotEnv;

$dot = new DotEnv(__DIR__.'/');
$dot->load();

var_dump($_ENV['CI_ENVIRONMENT'] ?? null); 