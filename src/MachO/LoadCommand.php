<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class LoadCommand implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdsize;
    #[PackItem(offset: 0x08, type: 'char[16]')]
    public string $segmentName;
}
