<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class DYLDInfoCommand extends LoadCommand
{
    /** @var int $cmd LC_DYLD_INFO 0x22 or LC_DYLD_INFO_ONLY 0x80000022 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $rebaseOff;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $rebaseSize;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $bindOff;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $bindSize;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $weakBindOff;
    #[PackItem(offset: 0x1C, type: 'uint32')]
    public int $weakBindSize;
    #[PackItem(offset: 0x20, type: 'uint32')]
    public int $lazyBindOff;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $lazyBindSize;
    #[PackItem(offset: 0x28, type: 'uint32')]
    public int $exportOff;
    #[PackItem(offset: 0x2C, type: 'uint32')]
    public int $exportSize;
}
