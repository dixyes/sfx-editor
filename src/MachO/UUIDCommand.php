<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class UUIDCommand extends LoadCommand
{
    /** @var int $cmd LC_UUID 0x1B */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x18 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'char[16]')]
    public string $uuid;
}
