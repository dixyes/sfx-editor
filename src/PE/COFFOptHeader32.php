<?php

declare(strict_types=1);

namespace PE;

use \Exception;

use Unpacker\CommonPack;
use Unpacker\Unpacker;
use Unpacker\PackItem;

class COFFOptHeader32 implements CommonPack
{
    use Unpacker;

    // COFF optional header (not optional for executable)
    #[PackItem(offset: 0x00, type: "uint16")]
    public int $magic; // 0x10b IMAGE_NT_OPTIONAL_HDR32_MAGIC
    #[PackItem(offset: 0x02, type: "uint8")]
    public int $majorLinkerVersion;
    #[PackItem(offset: 0x03, type: "uint8")]
    public int $minorLinkerVersion;
    #[PackItem(offset: 0x04, type: "uint32")]
    public int $sizeOfCode;
    #[PackItem(offset: 0x08, type: "uint32")]
    public int $sizeOfInitializedData;
    #[PackItem(offset: 0x0c, type: "uint32")]
    public int $sizeOfUninitializedData;
    #[PackItem(offset: 0x10, type: "uint32")]
    public int $addressOfEntryPoint;
    #[PackItem(offset: 0x14, type: "uint32")]
    public int $baseOfCode;
    #[PackItem(offset: 0x18, type: "uint32")]
    public int $baseOfData;
    #[PackItem(offset: 0x1c, type: "uint32")]
    public int $imageBase;
    #[PackItem(offset: 0x20, type: "uint32")]
    public int $sectionAlignment;
    #[PackItem(offset: 0x24, type: "uint32")]
    public int $fileAlignment;
    #[PackItem(offset: 0x28, type: "uint16")]
    public int $majorOperatingSystemVersion;
    #[PackItem(offset: 0x2a, type: "uint16")]
    public int $minorOperatingSystemVersion;
    #[PackItem(offset: 0x2c, type: "uint16")]
    public int $majorImageVersion;
    #[PackItem(offset: 0x2e, type: "uint16")]
    public int $minorImageVersion;
    #[PackItem(offset: 0x30, type: "uint16")]
    public int $majorSubsystemVersion;
    #[PackItem(offset: 0x32, type: "uint16")]
    public int $minorSubsystemVersion;
    #[PackItem(offset: 0x34, type: "uint32")]
    public int $win32VersionValue;
    #[PackItem(offset: 0x38, type: "uint32")]
    public int $sizeOfImage;
    #[PackItem(offset: 0x3c, type: "uint32")]
    public int $sizeOfHeaders;
    #[PackItem(offset: 0x40, type: "uint32")]
    public int $checkSum;
    #[PackItem(offset: 0x44, type: "uint16")]
    public int $subsystem;
    #[PackItem(offset: 0x46, type: "uint16")]
    public int $dllCharacteristics;
    #[PackItem(offset: 0x48, type: "uint32")]
    public int $sizeOfStackReserve;
    #[PackItem(offset: 0x4c, type: "uint32")]
    public int $sizeOfStackCommit;
    #[PackItem(offset: 0x50, type: "uint32")]
    public int $sizeOfHeapReserve;
    #[PackItem(offset: 0x54, type: "uint32")]
    public int $sizeOfHeapCommit;
    #[PackItem(offset: 0x58, type: "uint32")]
    public int $loaderFlags;
    #[PackItem(offset: 0x5c, type: "uint32")]
    public int $numberOfRvaAndSizes;
    #[PackItem(offset: 0x60, type: "DataDirectories[]", size: '$this->numberOfRvaAndSizes')]
    /** @var $dataDirectories DataDirectory[] */
    public array $dataDirectories;

    public function verify(): void
    {
        if ($this->magic !== 0x20b) {
            throw new Exception('Invalid magic');
        }
        if ($this->numberOfRvaAndSizes != 16) {
            throw new Exception('Invalid number of RVA and sizes');
        }
    }

    public function resum(int $offset): void
    {
        // do nothing
    }
}
