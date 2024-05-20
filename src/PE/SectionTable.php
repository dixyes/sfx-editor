<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\NullVerifier;
use Unpacker\CommonPack;

class SectionTable implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[]', size: 8)]
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
    public int $numberOfRelocations;
    #[PackItem(offset: 0x22, type: 'uint16')]
    public int $numberOfLinenumbers;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $characteristics;

}
