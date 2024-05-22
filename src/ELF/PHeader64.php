<?php

declare(strict_types=1);

namespace ELF;


use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class PHeader64 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $type;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $flags;
    #[PackItem(offset: 0x08, type: 'uint64')]
    public int $offset;
    #[PackItem(offset: 0x10, type: 'uint64')]
    public int $vaddr;
    #[PackItem(offset: 0x18, type: 'uint64')]
    public int $paddr;
    #[PackItem(offset: 0x20, type: 'uint64')]
    public int $filesz;
    #[PackItem(offset: 0x28, type: 'uint64')]
    public int $memsz;
    #[PackItem(offset: 0x30, type: 'uint64')]
    public int $align;
}
