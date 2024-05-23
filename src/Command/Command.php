<?php

class Command
{
    public function __construct(
        public string $name,
        public string $description,
        public array $args = [],
    ) {
    }
}
