<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class SymbolTableCommand extends LoadCommand
{
    /** @var int $cmd LC_SYMTAB 0x02 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x18 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $symbolOff;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $nSymbols;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $stringOff;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $stringSize;
}
