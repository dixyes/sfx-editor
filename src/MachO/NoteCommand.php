<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class NoteCommand extends LoadCommand
{
    /** @var int $cmd LC_NOTE 0x31 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $dataOff;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $dataSize;
}
