<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class DummyCommand extends LoadCommand
{
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'char[]', size: '$this->cmdSize - 8')]
    public string $data;

    // public function __debugInfo(): array
    // {
    //     return [
    //         'cmd' => $this->cmd,
    //         'cmdSize' => $this->cmdSize,
    //         'data' => bin2hex($this->data),
    //     ];
    // }
}
