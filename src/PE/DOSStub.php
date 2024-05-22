<?php

declare(strict_types=1);

namespace PE;

use Unpacker\CommonPack;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\NullVerifier;

class DOSStub implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    /**
     * exe built by msvc will use a huge stub with "Rich header" which contains toolchain info 
     * this stub do stub work without that header
     */
    const SHORT_STUB = "\x0E\x1F\xBA\x0E\x00\xB4\x09\xCD\x21\xB8\x01\x4C\xCD\x21Nope!\r\r\n\$\0\0\0\0\0\0\0\0\0";

    public function __construct(
        public readonly int $size
    ) {
    }

    #[PackItem(offset: 0, type: 'char[$this->size]')]
    public string $stub;
}
