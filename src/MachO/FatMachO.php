<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\FatArch;

class FatMachO implements CommonPack
{
    const FAT_MAGIC = 0xcafebabe;
    const FAT_CIGAM = 0xbebafeca;

    use NullVerifier;
    use Unpacker {
        unpack as _unpack;
        pack as _pack;
    }

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $magic;
    #[PackItem(offset: 0x04, type: 'uint32be')]
    public int $nArchs;
    #[PackItem(offset: 0x08, type: 'FatArch[$this->nArchs]')]
    public array $archs;

    public string $padding;
    /**
     * @var MachOFile[]
     */
    public array $machos;
    public string $payload;

    public function unpack(string $remaining): int
    {
        $consume = $this->_unpack($remaining);
        $this->machos = [];

        $this->padding = substr($remaining, $consume, $this->archs[0]->fileOffset - $consume);

        $this->machos = [];
        $end = null;
        foreach ($this->archs as $arch) {
            $macho = new MachOFile();
            $align = 1 << $arch->align;
            if ($end !== null) {
                if ($end !== $arch->fileOffset) {
                    throw new \Exception("Invalid fat macho");
                }
            }
            $end = ($arch->fileOffset + $arch->size + $align - 1) & ~($align - 1);
            $consume = $macho->unpack(substr(
                    $remaining,
                    $arch->fileOffset,
                    $end - $arch->fileOffset
                )
            );
            $this->machos[] = $macho;
        }
        $this->payload = substr($remaining, $end);

        return strlen($remaining);
    }

    public function pack(): string
    {
        $data = $this->_pack();
        $data .= $this->padding;
        foreach ($this->machos as $macho) {
            $data .= $macho->pack();
        }
        $data .= $this->payload;
        return $data;
    }
}
