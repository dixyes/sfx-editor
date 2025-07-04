<?php

require_once __DIR__ . '/../autoload.php';

$arm64File = file_get_contents('micro.sfx.arm64');
$x86_64File = file_get_contents('micro.sfx.x86_64');

// unpack mach-os
$arm64 = new \MachO\MachOFile();
$arm64->unpack($arm64File);
$x86_64 = new \MachO\MachOFile();
$x86_64->unpack($x86_64File);

// create fat mach-o
$fat = \MachO\FatMachO::createEmpty();
$end = 0x8 /* sizeof(fat MachO header) */ + 2 * 0x14 /* sizeof(FatArch) */;
foreach ([$x86_64, $arm64] as $i => $macho) {
    $arch = new \MachO\FatArch();
    $fat->archs[] = $arch;
    $fat->nArchs++;

    // lipo use 1 << 13 (8192) for x86_64 alignment, don't know why / how to get it
    // we use max section alignment here
    $align = 0;
    foreach ($macho->header->loadCommands as $cmd) {
        if ($cmd instanceof \MachO\SegmentCommand32 || $cmd instanceof \MachO\SegmentCommand64) {
            foreach ($cmd->sections as $section) {
                $align = max($align, $section->align);
            }
        }
    }

    $data = $macho->pack();

    $arch->cpuType = $macho->header->cpuType;
    $arch->cpuSubtype = $macho->header->cpuSubtype;
    $fileOffset = ($end + (1 << $align) - 1) & ~((1 << $align) - 1);
    $fat->paddings[] = str_repeat("\0", $fileOffset - $end);
    $arch->fileOffset = $fileOffset;
    $arch->size = strlen($data);
    $arch->align = $align;
    $end = $fileOffset + $arch->size;
    $fat->machos[] = $macho;
}

$packed = $fat->pack();
file_put_contents('micro.sfx.universal', $packed);
chmod('micro.sfx.universal', 0755);
