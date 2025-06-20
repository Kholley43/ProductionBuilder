<?php
require __DIR__.'/vendor/codeigniter4/framework/system/Config/DotEnv.php';
use CodeIgniter\Config\DotEnv;
(new DotEnv(__DIR__.'/'))->load();
var_dump($_ENV['encryption.key'] ?? null); 