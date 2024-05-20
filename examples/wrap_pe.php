<?php

require_once(__DIR__ . '/../autoload.php');

$peFile = file_get_contents('./some.exe');
$pe = new PE\PEFile();
$pe->unpack($peFile);
file_put_contents('repackonly.exe', $pe->pack());

$pe->wrapPayload();

file_put_contents('wrapped.exe', $pe->pack());
