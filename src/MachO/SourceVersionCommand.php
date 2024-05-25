<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class SourceVersionCommand extends LoadCommand
{
    /** @var int $cmd LC_SOURCE_VERSION 0x2A */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x10 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint64')]
    public int $version;

    public function versionTuple(): array
    {
        $version = $this->version;
        $versionTuple = [];
        for ($i = 0; $i < 4; $i++) {
            $versionTuple[] = $version & 0x3FF;
            $version >>= 10;
        }
        $versionTuple[] = $version;
        array_reverse($versionTuple);
        return $versionTuple;
    }

    public function __debugInfo(): array
    {
        return [
            'cmd' => $this->cmd,
            'cmdSize' => $this->cmdSize,
            'version' => $this->version,
            'versionTuple' => $this->versionTuple(),
        ];
    }
}
