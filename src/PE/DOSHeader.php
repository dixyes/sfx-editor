<?php

declare(strict_types=1);

namespace PE;

use Exception;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;

class DOSHeader implements CommonPack
{
    use Unpacker;

    #[PackItem(offset: 0x00, type: "char[2]")]
    public string $signature;
    #[PackItem(offset: 0x02, type: "uint16")]
    public int $lastPageSize;
    #[PackItem(offset: 0x04, type: "uint16")]
    public int $numPages;
    #[PackItem(offset: 0x06, type: "uint16")]
    public int $numRelocations;
    #[PackItem(offset: 0x08, type: "uint16")]
    public int $headerSize;
    #[PackItem(offset: 0x0a, type: "uint16")]
    public int $minAlloc;
    #[PackItem(offset: 0x0c, type: "uint16")]
    public int $maxAlloc;
    #[PackItem(offset: 0x0e, type: "uint16")]
    public int $initialSS;
    #[PackItem(offset: 0x10, type: "uint16")]
    public int $initialSP;
    #[PackItem(offset: 0x12, type: "uint16")]
    public int $checksum;
    #[PackItem(offset: 0x14, type: "uint16")]
    public int $initialIP;
    #[PackItem(offset: 0x16, type: "uint16")]
    public int $initialCS;
    #[PackItem(offset: 0x18, type: "uint16")]
    public int $relocationTableOffset;
    #[PackItem(offset: 0x1a, type: "uint16")]
    public int $overlayNumber;
    // #[PackItem(offset: 0x1c?)]
    // public DosOverlay $overlays // TODO? or not todo?
    #[PackItem(offset: 0x1c, type: "uint64")]
    public int $reserved;
    #[PackItem(offset: 0x24, type: "uint16")]
    public int $oemId; // not used
    #[PackItem(offset: 0x26, type: "uint16")]
    public int $oemInfo; // not used
    #[PackItem(offset: 0x28, type: "char[20]")]
    public string $reserved2;
    #[PackItem(offset: 0x3c, type: "uint32")]
    public int $peHeaderOffset;


    public function verify(): void
    {
        if ($this->signature !== 'MZ') {
            throw new Exception("unsupported sign {$this->signature}");
        }
        if ($this->overlayNumber > 0) {
            throw new Exception("not implemented dos overlays");
        }
    }

    public function resum(int $offset): void
    {
        // checksum is not used, so do nothing
    }
}
