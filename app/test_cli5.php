<?php
// replicate spark script

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\ob_start();
$exit = \CodeIgniter\Boot::bootSpark($paths);
$out = \ob_get_clean();
file_put_contents(__DIR__.'/boot_out.txt',$out."\nExitCode={$exit}\n"); 