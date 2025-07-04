<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use MachO\FatArch;

class FatMachO implements CommonPack
{
    const FAT_MAGIC = 0xcafebabe;
    const FAT_CIGAM = 0xbebafeca;

    use NullVerifier;
    use Unpacker {
        unpack as _unpack;
        pack as _pack;
    }

    #[PackItem(offset: 0x00, type: 'uint32be')]
    public int $magic;
    #[PackItem(offset: 0x04, type: 'uint32be')]
    public int $nArchs;
    #[PackItem(offset: 0x08, type: 'FatArch[$this->nArchs]')]
    public array $archs;

    /**
     * @var MachOFile[]
     */
    public array $machos;
    /**
     * @var string[] padding for each macho
     */
    public array $paddings;
    public string $payload;

    public function unpack(string $remaining): int
    {
        $end = $this->_unpack($remaining);

        $this->machos = [];
        foreach ($this->archs as $arch) {
            $padding = substr($remaining, $end, $arch->fileOffset - $end);
            $end = $arch->fileOffset + $arch->size;
            $this->paddings[] = $padding;
            $macho = new MachOFile();
            $machoData = substr(
                $remaining,
                $arch->fileOffset,
                $arch->size
            );
            $macho->unpack($machoData);
            $this->machos[] = $macho;
        }
        $this->payload = substr($remaining, $end);

        // always consume all data
        return strlen($remaining);
    }

    public function pack(): string
    {
        $data = $this->_pack();
        foreach ($this->machos as $i => $macho) {
            $data .= $this->paddings[$i];
            $data .= $macho->pack();
        }
        $data .= $this->payload;
        return $data;
    }

    public function wrapPayload(): void
    {
        if ($this->payload === "") {
            throw new \Exception("Payload is empty, is executable already wrapped?");
        }
        if (strlen($this->paddings[0]) < 0x14 /* sizeof(FatArch) */) {
            // impossible practically, the first padding should be at least 4k - fat header size
            // usually only 2 arches, so we have enough space to add a new arch
            // so we don't do adjust here, just throw an exception if this really happens
            throw new \Exception("Padding is too short");
        }
        $this->paddings[0] = substr($this->paddings[0], 0x14);
        $lastArch = $this->archs[$this->nArchs - 1];

        $payloadArch = new FatArch();
        $this->archs[] = $payloadArch;
        $this->nArchs++;
        // assert($this->nArchs === count($this->archs));
        $payloadArch->cpuType = MachOHeader::CPU_TYPE_X86;
        $payloadArch->cpuSubtype = 0;
        $payloadArch->align = 12; // 4k page align
        $fileOffset = $lastArch->fileOffset + $lastArch->size;
        $fileOffset = ($fileOffset + 0xfff) & ~0xfff;
        $payloadArch->fileOffset = $fileOffset;
        $payloadArch->size = 0x1000 + 16 + strlen($this->payload);
        $this->paddings[] = str_repeat("\0", $fileOffset - ($lastArch->fileOffset + $lastArch->size));
        $this->machos[] = MachOFile::createFakeMachO($fileOffset, $this->payload);

        $this->payload = "";
    }

    static public function createEmpty(): static
    {
        $fat = new static();
        $fat->magic = self::FAT_MAGIC;
        $fat->nArchs = 0;
        $fat->archs = [];
        $fat->machos = [];
        $fat->paddings = [];
        $fat->payload = "";
        return $fat;
    }
}
