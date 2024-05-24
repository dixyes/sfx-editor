<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class SegmentCommand32 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    /** @var int $cmd LC_SEGMENT 0x1 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x38 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'char[16]')]
    public string $name;

    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $vmAddr;
    #[PackItem(offset: 0x1C, type: 'uint32')]
    public int $vmSize;
    #[PackItem(offset: 0x20, type: 'uint32')]
    public int $fileOffset;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $fileSize;
    
    #[PackItem(offset: 0x28, type: 'uint32')]
    public int $maxProtect;
    #[PackItem(offset: 0x2C, type: 'uint32')]
    public int $initProtect;
    #[PackItem(offset: 0x30, type: 'uint32')]
    public int $nSections;
    #[PackItem(offset: 0x34, type: 'uint32')]
    public int $flags;
}
