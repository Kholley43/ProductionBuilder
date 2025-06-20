<?php
$bytes = file_get_contents('sparkout.txt');
foreach(str_split($bytes) as $c){ printf('%02X ', ord($c)); }
echo "\n"; 