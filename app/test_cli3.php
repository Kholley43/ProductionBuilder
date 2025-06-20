<?php
require __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
require __DIR__ . '/vendor/codeigniter4/framework/system/CLI/CLI.php';

\CodeIgniter\CLI\CLI::write('CLI write message');

$io = new \CodeIgniter\CLI\InputOutput();
$io->fwrite(STDOUT, "IO direct message\n"); 