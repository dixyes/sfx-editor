<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class UnixThreadCommandX86 extends LoadCommand
{
    const i386_THREAD_STATE = 1;
    const i386_THREAD_STATE_COUNT = 0x10;
    // const i386_FLOAT_STATE = 2;
    // const i386_EXCEPTION_STATE = 3;

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

    /** @var int[] $registers i386_THREAD_STATE: eax, ebx, ecx, edx, edi, esi, ebp, esp, ss, eflags, eip, cs, ds, es, fs, gs */
    #[PackItem(offset: 0x10, type: 'uint32[$this->count]')]
    public array $registers;

    static public function createEmpty(): static
    {
        $cmd = new static();
        $cmd->cmd = LoadCommand::LC_UNIXTHREAD;
        $cmd->cmdSize = 0x50 /* 0x08 + 0x08 + 0x10 * 4 */;
        $cmd->flavor = static::i386_THREAD_STATE;
        $cmd->count = static::i386_THREAD_STATE_COUNT;
        $cmd->registers = array_fill(0, static::i386_THREAD_STATE_COUNT, 0);
        return $cmd;
    }
}
