<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class DynamicSymbolTableCommand extends LoadCommand
{
    /** @var int $cmd LC_DYSYMTAB 0x0B */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x18 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $iLocalSymbol;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $nLocalSymbol;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $iExternDefinedSymbol;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $nExternDefinedSymbol;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $iUndefinedSymbol;
    #[PackItem(offset: 0x1C, type: 'uint32')]
    public int $nUndefinedSymbol;
    #[PackItem(offset: 0x20, type: 'uint32')]
    public int $tocOff;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $nTOC;
    #[PackItem(offset: 0x28, type: 'uint32')]
    public int $moduleTableOff;
    #[PackItem(offset: 0x2C, type: 'uint32')]
    public int $nModuleTable;
    #[PackItem(offset: 0x30, type: 'uint32')]
    public int $externReferencedSymbolOff;
    #[PackItem(offset: 0x34, type: 'uint32')]
    public int $nExternReferencedSymbols;
    #[PackItem(offset: 0x38, type: 'uint32')]
    public int $indirectSymbolOff;
    #[PackItem(offset: 0x3C, type: 'uint32')]
    public int $nIndirectSymbols;
    #[PackItem(offset: 0x40, type: 'uint32')]
    public int $externRelocationOff;
    #[PackItem(offset: 0x44, type: 'uint32')]
    public int $nExternRelocations;
    #[PackItem(offset: 0x48, type: 'uint32')]
    public int $localRelocationOff;
    #[PackItem(offset: 0x4C, type: 'uint32')]
    public int $nLocalRelocations;
}
