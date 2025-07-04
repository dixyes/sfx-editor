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
        unpack as _unpack;
        pack as _pack;
    }
    use NullVerifier;

    const MH_MAGIC = 0xfeedface;
    const MH_CIGAM = 0xcefaedfe;
    const MH_MAGIC_64 = 0xfeedfacf;
    const MH_CIGAM_64 = 0xcffaedfe;

    const MH_OBJECT = 0x1;
    const MH_EXECUTE = 0x2;
    const MH_FILESET = 0xc;

    const CPU_TYPE_X86 = 0x07;
    const CPU_TYPE_MIPS = 0x08;
    const CPU_TYPE_X86_64 = 0x01000007;
    const CPU_TYPE_ARM = 0x0C;
    const CPU_TYPE_ARM64 = 0x0100000C;

    const LC_SEGMENT = 0x1;
    const LC_SEGMENT_64 = 0x19;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $magic;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cpuType;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $cpuSubtype;
    #[PackItem(offset: 0x0c, type: 'uint32')]
    public int $fileType;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $nCmds;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $sizeOfCmds;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $flags;
    #[PackItem(offset: 0x1c, type: 'uint32', cond: '$this->cpuType & 0x01000000')]
    public int $reserved;

    /**
     * @var LoadCommand[]
     */
    public array $loadCommands;

    public function unpack(string $remaining): int
    {
        $consume = $this->_unpack($remaining);
        $this->loadCommands = [];
        $remaining = substr($remaining, $consume);
        for ($i = 0; $i < $this->nCmds; $i++) {
            $cmd = LoadCommand::fromData($remaining, $this->cpuType);
            $remaining = substr($remaining, $cmd->cmdSize);
            $consume += $cmd->cmdSize;
            $this->loadCommands[] = $cmd;
        }
        return $consume;
    }

    public function pack(): string
    {
        $data = $this->_pack();
        foreach ($this->loadCommands as $cmd) {
            $data .= $cmd->pack();
        }
        return $data;
    }
}
