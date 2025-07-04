<?php

require_once __DIR__ . '/../autoload.php';

// open fat mach-o
$fatFile = file_get_contents('micro.sfx.universal');

// add payload
$fatFile .= "text<?php echo 'hello'; ?>end";

// unpack fat mach-o
$fat = new \MachO\FatMachO();
$fat->unpack($fatFile);

// repack fat mach-o
$repacked = $fat->pack();
file_put_contents('repackonly', $repacked);
chmod('repackonly', 0755);

// wrap payload
$fat->wrapPayload();

// repack fat mach-o
$repacked = $fat->pack();
file_put_contents('wrapped', $repacked);
chmod('wrapped', 0755);
