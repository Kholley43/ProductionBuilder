<?php
require __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
require __DIR__ . '/vendor/codeigniter4/framework/system/CLI/InputOutput.php';
$io = new \CodeIgniter\CLI\InputOutput();
$io->fwrite(STDOUT, "Message from InputOutput\n");
fwrite(STDOUT, "Message from fwrite direct\n"); 