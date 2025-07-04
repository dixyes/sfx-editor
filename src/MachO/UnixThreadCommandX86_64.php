<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class UnixThreadCommandX86_64 extends LoadCommand
{
    const x86_THREAD_STATE64 = 1;
    const x86_THREAD_STATE64_COUNT = 21;

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

    /** @var int[] $registers x86_THREAD_STATE64: rax, rbx, rcx, rdx, rdi, rsi, rbp, rsp, r8, r9, r10, r11, r12, r13, r14, r15, rip, rflags, cs, fs, gs */
    #[PackItem(offset: 0x10, type: 'uint64[$this->count]')]
    public array $registers;
}
