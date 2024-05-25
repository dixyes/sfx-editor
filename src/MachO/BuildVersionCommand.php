<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class BuildVersionCommand extends LoadCommand
{
    /** @var int $cmd LC_BUILD_VERSION 0x32 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $platform;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $minOS;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $sdk;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $nTools;

    /** @var BuildToolVersion[] $tools */
    #[PackItem(offset: 0x18, type: 'BuildToolVersion[]', size: '$this->nTools')]
    public array $buildToolVersions;
}
