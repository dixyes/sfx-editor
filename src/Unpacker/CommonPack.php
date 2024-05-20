<?php

declare(strict_types=1);

namespace Unpacker;

interface CommonPack
{
    public function unpack(string $remaining): int;
    public function pack(): string;

    public function verify(): void;
    public function resum(int $offset): void;
}
