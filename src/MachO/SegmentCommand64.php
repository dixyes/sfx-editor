<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class SegmentCommand64 extends LoadCommand
{
    /** @var int $cmd LC_SEGMENT_64 0x19 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x48 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'char[16]')]
    public string $name;

    #[PackItem(offset: 0x18, type: 'uint64')]
    public int $vmAddr;
    #[PackItem(offset: 0x20, type: 'uint64')]
    public int $vmSize;
    #[PackItem(offset: 0x28, type: 'uint64')]
    public int $fileOffset;
    #[PackItem(offset: 0x30, type: 'uint64')]
    public int $fileSize;

    #[PackItem(offset: 0x38, type: 'uint32')]
    public int $maxProtect;
    #[PackItem(offset: 0x3C, type: 'uint32')]
    public int $initProtect;
    #[PackItem(offset: 0x40, type: 'uint32')]
    public int $nSections;
    #[PackItem(offset: 0x44, type: 'uint32')]
    public int $flags;

    #[PackItem(offset: 0x48, type: 'SegmentSection64[]', size: '$this->nSections')]
    public array $sections;
}
