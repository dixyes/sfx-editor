<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;

class SectionHeader
{
    use Unpacker;

    #[PackItem(offset: 0x00, type: 'char[8]')]
    public string $name;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $virtualSize;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $virtualAddress;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $sizeOfRawData;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $pointerToRawData;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $pointerToRelocations;
    #[PackItem(offset: 0x1C, type: 'uint32')]
    public int $pointerToLinenumbers;
    #[PackItem(offset: 0x20, type: 'uint16')]
    public int $numRelocations;
    #[PackItem(offset: 0x22, type: 'uint16')]
    public int $numLinenumbers;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $flags;
}