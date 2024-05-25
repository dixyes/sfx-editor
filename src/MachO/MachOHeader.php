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

    const MH_MAGIC_64 = 0xfeedfacf;
    const MH_CIGAM_64 = 0xcffaedfe;

    const CPU_TYPE_X86 = 0x07;
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

    public array $loadCommands;

    public function unpack(string $data): int
    {
        $consume = $this->_unpack($data);
        $this->loadCommands = [];
        $remaining = substr($data, $consume);
        for ($i = 0; $i < $this->nCmds; $i++) {
            $cmd = LoadCommand::fromData($remaining);
            $remaining = substr($remaining, $cmd->cmdSize);
            $this->loadCommands[] = $cmd;
        }
        return strlen($data) - strlen($remaining);
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
