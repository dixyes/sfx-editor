<?php

declare(strict_types=1);

namespace PE;

use Unpacker\CommonPack;
use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\PackItem;

class SectionHeader implements CommonPack
{

    const IMAGE_SCN_TYPE_NO_PAD = 0x00000008;
    const IMAGE_SCN_CNT_CODE = 0x00000020;
    const IMAGE_SCN_CNT_INITIALIZED_DATA = 0x00000040;
    const IMAGE_SCN_CNT_UNINITIALIZED_DATA = 0x00000080;
    const IMAGE_SCN_LNK_OTHER = 0x00000100;
    const IMAGE_SCN_LNK_INFO = 0x00000200;
    const IMAGE_SCN_LNK_REMOVE = 0x00000800;
    const IMAGE_SCN_LNK_COMDAT = 0x00001000;
    const IMAGE_SCN_GPREL = 0x00008000;
    const IMAGE_SCN_MEM_PURGEABLE = 0x00020000;
    const IMAGE_SCN_MEM_16BIT = 0x00020000;
    const IMAGE_SCN_MEM_LOCKED = 0x00040000;
    const IMAGE_SCN_MEM_PRELOAD = 0x00080000;
    const IMAGE_SCN_ALIGN_1BYTES = 0x00100000;
    const IMAGE_SCN_ALIGN_2BYTES = 0x00200000;
    const IMAGE_SCN_ALIGN_4BYTES = 0x00300000;
    const IMAGE_SCN_ALIGN_8BYTES = 0x00400000;
    const IMAGE_SCN_ALIGN_16BYTES = 0x00500000;
    const IMAGE_SCN_ALIGN_32BYTES = 0x00600000;
    const IMAGE_SCN_ALIGN_64BYTES = 0x00700000;
    const IMAGE_SCN_ALIGN_128BYTES = 0x00800000;
    const IMAGE_SCN_ALIGN_256BYTES = 0x00900000;
    const IMAGE_SCN_ALIGN_512BYTES = 0x00A00000;
    const IMAGE_SCN_ALIGN_1024BYTES = 0x00B00000;
    const IMAGE_SCN_ALIGN_2048BYTES = 0x00C00000;
    const IMAGE_SCN_ALIGN_4096BYTES = 0x00D00000;
    const IMAGE_SCN_ALIGN_8192BYTES = 0x00E00000;
    const IMAGE_SCN_LNK_NRELOC_OVFL = 0x01000000;
    const IMAGE_SCN_MEM_DISCARDABLE = 0x02000000;
    const IMAGE_SCN_MEM_NOT_CACHED = 0x04000000;
    const IMAGE_SCN_MEM_NOT_PAGED = 0x08000000;
    const IMAGE_SCN_MEM_SHARED = 0x10000000;
    const IMAGE_SCN_MEM_EXECUTE = 0x20000000;
    const IMAGE_SCN_MEM_READ = 0x40000000;
    const IMAGE_SCN_MEM_WRITE = 0x80000000;

    use Unpacker;
    use NullVerifier;

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

    public string $sectionData;
}
