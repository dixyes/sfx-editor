<?php

require_once(__DIR__ . '/../autoload.php');

$machoFile = file_get_contents('./someexe');
$macho = new MachO\MachOFile();
$macho->unpack($machoFile);
file_put_contents('repackonly', $macho->pack());

$macho->wrapPayload();

file_put_contents('wrapped', $macho->pack());

chmod('repackonly', 0755);
chmod('wrapped', 0755);
// wrapped should be codesign'd
