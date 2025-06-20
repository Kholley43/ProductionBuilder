<?php
require __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
require __DIR__ . '/vendor/codeigniter4/framework/system/CLI/CLI.php';

define('ENVIRONMENT', 'development');

\CodeIgniter\CLI\CLI::write('Check output'); 