<?php

declare(strict_types=1);

namespace Unpacker;

use Reflection;
use ReflectionClass;
use ReflectionNamedType;

trait Unpacker
{
    /**
     * @return array<int, array{0: array{'offset': int, 'type': string, 'size'?: int, 'if'?: string}, 1:\ReflectionProperty}>
     */
    public static function getPackInfo(): array
    {
        $packInfo = [];
        $thisClass = new ReflectionClass(static::class);
        $properties = $thisClass->getProperties();
        foreach ($properties as $property) {
            $attrs = $property->getAttributes(PackItem::class);
            if (count($attrs) === 0) {
                continue;
            }
            $attr = $attrs[0];
            $args = $attr->getArguments();
            $packInfo[$args['offset']] = [
                $args,
                $property,
            ];
        }
        return $packInfo;
    }

    // public readonly string $raw;

    public function unpack(string $remaining): int
    {
        // $packInfo [offset => args,reflectProperty]
        $packInfo = static::getPackInfo();

        $cursor = 0;

        foreach ($packInfo as $offset => [$args, $prop]) {
            if ($cursor != $offset) {
                throw new \Exception(sprintf(
                    "Expected offset %d, got %d on unpacing %s::%s",
                    $cursor,
                    $offset,
                    static::class,
                    $prop->getName(),
                ));
            }

            switch (true) {
                case $prop->getType()->getName() === "array":
                    preg_match('/^([a-zA-Z_][a-zA-Z0-9-_]*)\[(\d*|0x[0-9a-f]+|\$this->[a-zA-Z_][a-zA-Z0-9-_]*)\]$/', $args['type'], $matches);
                    if (!$matches || count($matches) !== 3) {
                        throw new \Exception(sprintf(
                            "Invalid type %s on unpacing %s::%s",
                            $args['type'],
                            static::class,
                            $prop->getName(),
                        ));
                    }

                    $type = $matches[1];
                    $size = $matches[2];

                    if (!str_starts_with($type, '\\')) {
                        $namespace = substr('\\' . static::class, 0, strrpos('\\' . static::class, '\\'));
                        $type = $namespace . '\\' . $type;
                    }
                    $class = new ReflectionClass($type);

                    // var_dump($size, str_starts_with($size, '$this->'));
                    if ($size === "") {
                        if (!isset($args['size'])) {
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacing %s::%s (no size specified)",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                            ));
                        }
                        $length = $args['size'];
                    } else if (str_starts_with($size, '$this->')) {
                        $propName = substr($size, 7);
                        if (!isset($this->$propName)) {
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacing %s::%s (property %s not found)",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                                $propName,
                            ));
                        }
                        $length = $this->$propName;
                    } else {
                        $length = intval($size, 0);
                    }

                    $value = [];
                    for ($i = 0; $i < $length; $i++) {
                        $obj = $class->newInstanceArgs($args['args'] ?? []);
                        $consumed = $obj->unpack(substr($remaining, $cursor));
                        $value[] = $obj;
                        $cursor += $consumed;
                    }

                    break;
                case $prop->getType()->getName() === "string":
                    preg_match('/^char\[(\d*|0x[0-9a-f]+|\$this->[a-zA-Z_][a-zA-Z0-9-_]*)\]$/', $args['type'], $matches);
                    if (!$matches || count($matches) !== 2) {
                        throw new \Exception(sprintf(
                            "Invalid type %s on unpacing %s::%s",
                            $args['type'],
                            static::class,
                            $prop->getName(),
                        ));
                    }

                    $size = $matches[1];

                    if ($size === "") {
                        if (!isset($args['size'])) {
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacing %s::%s (no size specified)",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                            ));
                        }
                        $length = $args['size'];
                    } else if (str_starts_with($size, '$this->')) {
                        $propName = substr($size, 7);
                        if (!isset($this->$propName)) {
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacing %s::%s (property %s not found)",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                                $propName,
                            ));
                        }
                        $length = $this->$propName;
                    } else {
                        $length = intval($size, 0);
                    }

                    $value = substr(
                        $remaining,
                        $cursor,
                        $length,
                    );

                    $cursor += $length;
                    break;
                case $prop->getType()->getName() === "int":
                    $size = 0;
                    $unpackArg = "";
                    switch ($args['type']) {
                        case "uint8":
                            $size = 1;
                            $unpackArg = "C";
                            break;
                        case "uint16":
                            $size = 2;
                            $unpackArg = "v";
                            break;
                        case "uint32":
                            $size = 4;
                            $unpackArg = "V";
                            break;
                        case "uint64":
                            $size = 8;
                            $unpackArg = "P";
                            break;
                        default:
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacing %s::%s",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                            ));
                    }

                    $value = unpack(
                        $unpackArg,
                        substr($remaining, $cursor, $size),
                    )[1];

                    $cursor += $size;
                    break;
                default:
                    throw new \Exception(sprintf(
                        "Invalid type %s on unpacing %s::%s",
                        $args['type'],
                        static::class,
                        $prop->getName(),
                    ));
            }

            $prop->setValue($this, $value);
        }

        // $this->raw = substr($remaining, 0, $cursor);

        return $cursor;
    }

    public function pack(): string
    {
        $packInfo = static::getPackInfo();

        $packArgs = "";
        $values = [];

        foreach ($packInfo as $offset => [$args, $prop]) {
            $value = $prop->getValue($this);
            switch (true) {
                case $prop->getType()->getName() === "array":
                    $content = "";
                    foreach ($value as $item) {
                        $content .= $item->pack();
                    }
                    $packArgs .= sprintf("a%d", strlen($content));
                    $value = $content;
                    break;
                case $prop->getType()->getName() === "string":
                    $packArgs .= sprintf("a%d", strlen($value));
                    break;
                case $prop->getType()->getName() === "int":
                    switch ($args['type']) {
                        case "uint8":
                            $packArgs .= "C";
                            break;
                        case "uint16":
                            $packArgs .= "v";
                            break;
                        case "uint32":
                            $packArgs .= "V";
                            break;
                        case "uint64":
                            $packArgs .= "P";
                            break;
                        default:
                            throw new \Exception(sprintf(
                                "Invalid type %s on packing %s::%s",
                                $args['type'],
                                static::class,
                                $prop->getName(),
                            ));
                    }
                    break;
                default:
                    throw new \Exception(sprintf(
                        "Invalid type %s on packing %s::%s",
                        $args['type'],
                        static::class,
                        $prop->getName(),
                    ));
            }
            $values[] = $value;
        }
        return pack($packArgs, ...$values);
    }
}
