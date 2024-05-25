<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\CommonPack;
use Unpacker\PackItem;
use Unpacker\Unpacker;
use Unpacker\NullVerifier;

class BuildToolVersion implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $tool;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $version;
}
