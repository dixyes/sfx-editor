<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class DummyCommand implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'char[]', size: '$this->cmdSize - 8')]
    public string $data;

    public function __debugInfo(): array
    {
        return [
            'cmd' => $this->cmd,
            'cmdSize' => $this->cmdSize,
            'data' => bin2hex($this->data),
        ];
    }
}
