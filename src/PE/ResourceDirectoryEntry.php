<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

/**
 * @note this class should not be used directly, use RSRC to parse RSRC section
 */
class ResourceDirectoryEntry implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $nameOrId;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $offsetToData;

    /** @var string|null $name utf16 string */
    public ?string $name = null;

    // debug only
    // public int $offset;
    public ResourceDirectory|ResourceDataEntry $item;

}
