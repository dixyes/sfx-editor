<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class SegmentSection64 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[16]')]
    public string $name;
    #[PackItem(offset: 0x10, type: 'char[16]')]
    public string $segmentName;
    /** @var int $addr vma */
    #[PackItem(offset: 0x20, type: 'uint64')]
    public int $addr;
    /** @var int $size vm size */
    #[PackItem(offset: 0x28, type: 'uint64')]
    public int $size;
    /** @var int $offset foa */
    #[PackItem(offset: 0x30, type: 'uint32')]
    public int $offset;
    #[PackItem(offset: 0x34, type: 'uint32')]
    public int $align;
    #[PackItem(offset: 0x38, type: 'uint32')]
    public int $relOff;
    #[PackItem(offset: 0x3C, type: 'uint32')]
    public int $nReloc;
    #[PackItem(offset: 0x40, type: 'uint32')]
    public int $flags;
    #[PackItem(offset: 0x44, type: 'uint32')]
    public int $reserved1;
    #[PackItem(offset: 0x48, type: 'uint32')]
    public int $reserved2;
    #[PackItem(offset: 0x4C, type: 'uint32')]
    public int $reserved3;
}
