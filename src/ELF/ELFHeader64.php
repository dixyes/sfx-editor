<?php

declare(strict_types=1);

namespace ELF;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class ELFHeader64 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[4]')]
    public string $magic;
    #[PackItem(offset: 0x04, type: 'uint8')]
    public int $class;
    #[PackItem(offset: 0x05, type: 'uint8')]
    public int $data;
    #[PackItem(offset: 0x06, type: 'uint8')]
    public int $identVersion;
    #[PackItem(offset: 0x07, type: 'uint8')]
    public int $osABI;
    #[PackItem(offset: 0x08, type: 'uint8')]
    public int $abiVersion;
    #[PackItem(offset: 0x09, type: 'char[7]')]
    public string $identPad;
    #[PackItem(offset: 0x10, type: 'uint16')]
    public int $type;
    #[PackItem(offset: 0x12, type: 'uint16')]
    public int $machine;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $version;
    #[PackItem(offset: 0x18, type: 'uint64')]
    public int $entry;
    #[PackItem(offset: 0x20, type: 'uint64')]
    public int $phOffset;
    #[PackItem(offset: 0x28, type: 'uint64')]
    public int $shOffset;
    #[PackItem(offset: 0x30, type: 'uint32')]
    public int $flags;
    #[PackItem(offset: 0x34, type: 'uint16')]
    public int $ehSize;
    #[PackItem(offset: 0x36, type: 'uint16')]
    public int $phEntrySize;
    #[PackItem(offset: 0x38, type: 'uint16')]
    public int $phNum;
    #[PackItem(offset: 0x3a, type: 'uint16')]
    public int $shEntrySize;
    #[PackItem(offset: 0x3c, type: 'uint16')]
    public int $shNum;
    #[PackItem(offset: 0x3e, type: 'uint16')]
    public int $shStringIndex;

    public function verify(): void
    {
        if ($this->magic !== "\x7fELF") {
            throw new \Exception('Invalid ELF magic');
        }
        if ($this->ehSize !== 0x40) {
            throw new \Exception('Invalid ELF header size');
        }
    }
}
