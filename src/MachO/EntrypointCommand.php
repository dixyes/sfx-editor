<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class EntrypointCommand extends LoadCommand
{
    /** @var int $cmd LC_MAIN 0x28 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x10 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint64')]
    public int $entryOff;
    #[PackItem(offset: 0x10, type: 'uint64')]
    public int $stackSize;
}
