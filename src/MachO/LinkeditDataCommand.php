<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class LinkeditDataCommand extends LoadCommand
{
    /** @var int $cmd LC_CODE_SIGNATURE 0x1D or LC_SEGMENT_SPLIT_INFO 0x1E or
     *  LC_FUNCTION_STARTS 0x26 or LC_DATA_IN_CODE 0x29 or
     *  LC_DYLIB_CODE_SIGN_DRS 0x2B or LC_LINKER_OPTIMIZATION_HINT 0x2E or
     *  LC_DYLD_EXPORTS_TRIE 0x80000033 or LC_DYLD_CHAINED_FIXUPS 0x80000034 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $dataOff;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $dataSize;
}
