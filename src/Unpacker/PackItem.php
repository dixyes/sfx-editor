<?php

declare(strict_types=1);

namespace Unpacker;

class PackItem
{
    public function __construct(
        public int $offset,
        public string $type,
        public int $size = 0,
        public array $args = [],
    ) {
    }
}
