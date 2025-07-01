<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;
use Unpacker\Unpacker;

class FatArch implements CommonPack
{

    use NullVerifier;
    use Unpacker;

    #[PackItem(offset: 0x00, type: 'uint32be')]
    public int $cpuType;
    #[PackItem(offset: 0x04, type: 'uint32be')]
    public int $cpuSubtype;
    #[PackItem(offset: 0x08, type: 'uint32be')]
    public int $fileOffset;
    #[PackItem(offset: 0x0c, type: 'uint32be')]
    public int $size;
    /**
     * @var int alignment power of 2
     */
    #[PackItem(offset: 0x10, type: 'uint32be')]
    public int $align;
}
