<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;

/**
 * IMAGE_DATA_DIRECTORY 
 */
class DataDirectory
{
    use Unpacker;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $virtualAddress;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $size;
}
