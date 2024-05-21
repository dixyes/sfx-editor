<?php

require_once(__DIR__ . '/../autoload.php');

$peFile = file_get_contents('./micro.sfx');
$pe = new PE\PEFile();
$pe->unpack($peFile);
file_put_contents('repackonly.exe', $pe->pack());

$pe->fixRSRC();

file_put_contents('fixed.exe', $pe->pack());
