<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class MachOHeader implements CommonPack
{
    use Unpacker {
        unpack as private _unpack;
    }
    use NullVerifier;

    const CPU_TYPE_X86 = 0x07;
    const CPU_TYPE_X86_64 = 0x01000007;
    const CPU_TYPE_ARM = 0x0C;
    const CPU_TYPE_ARM64 = 0x0100000C;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $magic;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cpuType;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $cpuSubtype;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $fileType;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $nCmds;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $sizeOfCmds;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $flags;

    public function unpack(string $data): int
    {
        $ret = $this->_unpack($data);
        if ($this->cpuType | 0x01000000) {
            // is 64-bit, there's a reserved field
            $ret += 4;
        }
        return $ret;
    }
}
