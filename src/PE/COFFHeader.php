<?php

declare(strict_types=1);

namespace PE;

use \Exception;
use Unpacker\CommonPack;
use Unpacker\Unpacker;
use Unpacker\PackItem;

class COFFHeader implements CommonPack
{
    use Unpacker;

    const IMAGE_FILE_MACHINE_AMD64 = 0x8664;
    const IMAGE_FILE_MACHINE_I386 = 0x14c;
    const IMAGE_FILE_MACHINE_ARM64 = 0xAA64;
    // 别的架构没见过啊 用到了再说吧

    const IMAGE_FILE_RELOCS_STRIPPED = 1 << 0;
    const IMAGE_FILE_EXECUTABLE_IMAGE = 1 << 1;
    const IMAGE_FILE_LINE_NUMS_STRIPPED = 1 << 2;
    const IMAGE_FILE_LOCAL_SYMS_STRIPPED = 1 << 3;
    const IMAGE_FILE_AGGRESSIVE_WS_TRIM = 1 << 4;
    const IMAGE_FILE_LARGE_ADDRESS_AWARE = 1 << 5;
    const IMAGE_FILE_BYTES_REVERSED_LO = 1 << 7;
    const IMAGE_FILE_32BIT_MACHINE = 1 << 8;
    const IMAGE_FILE_DEBUG_STRIPPED = 1 << 9;
    const IMAGE_FILE_REMOVABLE_RUN_FROM_SWAP = 1 << 10;
    const IMAGE_FILE_NET_RUN_FROM_SWAP = 1 << 11;
    const IMAGE_FILE_SYSTEM = 1 << 12;
    const IMAGE_FILE_DLL = 1 << 13;
    const IMAGE_FILE_UP_SYSTEM_ONLY = 1 << 14;
    const IMAGE_FILE_BYTES_REVERSED_HI = 1 << 15;

    // COFF header
    #[PackItem(offset: 0, type: "char[4]")]
    public string $magic;
    #[PackItem(offset: 0x4, type: "uint16")]
    public int $machine;
    #[PackItem(offset: 0x6, type: "uint16")]
    public int $numberOfSections;
    #[PackItem(offset: 0x8, type: "uint32")]
    public int $timeDateStamp;
    #[PackItem(offset: 0xc, type: "uint32")]
    public int $pointerToSymbolTable; // deprecated
    #[PackItem(offset: 0x10, type: "uint32")]
    public int $numberOfSymbols; // deprecated
    #[PackItem(offset: 0x14, type: "uint16")]
    public int $sizeOfOptionalHeader;
    #[PackItem(offset: 0x16, type: "uint16")]
    public int $characteristics;

    public function isExecutable(): bool
    {
        return (bool) ($this->characteristics & self::IMAGE_FILE_EXECUTABLE_IMAGE);
    }

    public function verify(): void
    {
        if ($this->magic !== "PE\0\0") {
            throw new Exception('Invalid PE signature');
        }
        if (!in_array($this->machine, [
            self::IMAGE_FILE_MACHINE_AMD64,
            self::IMAGE_FILE_MACHINE_I386,
            self::IMAGE_FILE_MACHINE_ARM64,
        ])) {
            throw new Exception('Unsupported machine type');
        }
        if ($this->isExecutable() and $this->sizeOfOptionalHeader === 0) {
            throw new Exception('Optional header is required for executable');
        }
    }

    public function resum(int $offset): void
    {
        // no checksums
    }
}
