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
class ResourceDataEntry implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $dataRVA;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $size;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $codepage;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $reserved;

    // debug only
    // public int $offset;
    public string $data;
}
