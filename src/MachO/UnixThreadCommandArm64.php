<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class UnixThreadCommandArm64 extends LoadCommand
{
    const ARM_THREAD_STATE64 = 1;
    const ARM_THREAD_STATE64_COUNT = 34;

    /** @var int $cmd LC_UNIXTHREAD 0x5 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $flavor;
    #[PackItem(offset: 0x0c, type: 'uint32')]
    public int $count;

    /** @var int[] $registers ARM_THREAD_STATE64: x0-x28, fp, lr, sp, pc, (low:cpsr high:padding) */
    #[PackItem(offset: 0x10, type: 'uint64[$this->count]')]
    public array $registers;
}
