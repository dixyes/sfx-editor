<?php

declare(strict_types=1);

namespace Unpacker;

require_once __DIR__ . '/utilFunctions.php';

use Reflection;
use ReflectionClass;
use ReflectionNamedType;

trait Unpacker
{
    public static function getPackItems(): array
    {
        $packItems = [];
        $thisClass = new ReflectionClass(static::class);
        $properties = $thisClass->getProperties();
        foreach ($properties as $property) {
            $attrs = $property->getAttributes(PackItem::class);
            if (count($attrs) === 0) {
                // not a pack item
                continue;
            }
            foreach ($attrs as $attr) {
                /** @var array{
                 *      'offset': int,
                 *      'type': string,
                 *      'size'?: int,
                 *      'cond'?: array<string>,
                 *      'args'?: array<mixed>,
                 * } $attrArg */
                $attrArg = $attr->getArguments();
                if (isset($attrArg['cond']) && is_string($attrArg['cond'])) {
                    $attrArg['cond'] = [$attrArg['cond']];
                }
                $packInfo = [
                    $attrArg,
                    $property,
                ];
                $packItems[$attrArg['offset']][] = $packInfo;
            }
        }
        return $packItems;
    }

    // public readonly string $raw;

    public function unpack(string $remaining): int
    {
        $packItems = static::getPackItems();

        $cursor = 0;

        foreach ($packItems as $offset => $packInfos) {
            foreach ($packInfos as [$arg, $prop]) {
                if (isset($arg['cond'])) {
                    $useThis = true;
                    foreach ($arg['cond'] as $cond) {
                        $ast = ConditionParse::parse($cond);
                        $condValue = executeAST($ast, [
                            // 感觉用不到，先注释了
                            // '$data' => $remaining,
                            // '$rem' => substr($remaining, $cursor),
                            // '$off' => $cursor,
                            '$this' => $this,
                        ]);
                        // var_dump($condValue, $ast, $cond, $remaining, $cursor, $arg['cond']);
                        if (!$condValue) {
                            $useThis = false;
                            break;
                        }
                    }
                    if (!$useThis) {
                        continue;
                    }
                }

                $propName = $prop->getName();

                if (!is_int($offset)) {
                    throw new \Exception(sprintf(
                        "Invalid offset %s on unpacking %s::%s",
                        $offset,
                        static::class,
                        $propName,
                    ));
                }

                if ($cursor != $offset) {
                    throw new \Exception(sprintf(
                        "Expected offset %d, got %d on unpacking %s::%s",
                        $cursor,
                        $offset,
                        static::class,
                        $propName,
                    ));
                }
                // printf("Unpacking %s::%s at offset %d\n", static::class, $propName, $cursor);

                switch (true) {
                    case $prop->getType()->getName() === "array":
                        // TODO: 拿PEG/递归下降parser来搞下type的解析
                        preg_match('/^([a-zA-Z_][a-zA-Z0-9-_]*)\[(\d*|0x[0-9a-f]+|\$this->[a-zA-Z_][a-zA-Z0-9-_]*)\]$/', $arg['type'], $matches);
                        if (!$matches || count($matches) !== 3) {
                            throw new \Exception(sprintf(
                                "Invalid property type %s on unpacking %s::%s",
                                $arg['type'],
                                static::class,
                                $propName,
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
                            if (!isset($arg['size'])) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (no size specified)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                ));
                            }
                            $length = $arg['size'];
                            if (is_string($length)) {
                                $ast = ConditionParse::parse($length);
                                $length = executeAST($ast, [
                                    '$this' => $this,
                                ]);
                            }
                            if (!is_int($length)) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (size is not int)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                ));
                            }
                        } else if (str_starts_with($size, '$this->')) {
                            $typePropName = substr($size, 7);
                            if (!isset($this->$typePropName)) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (property %s not found)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                    $typePropName,
                                ));
                            }
                            $length = $this->$typePropName;
                        } else {
                            $length = intval($size, 0);
                        }

                        $value = [];
                        for ($i = 0; $i < $length; $i++) {
                            $obj = $class->newInstanceArgs($arg['args'] ?? []);
                            $consumed = $obj->unpack(substr($remaining, $cursor));
                            $value[] = $obj;
                            $cursor += $consumed;
                        }

                        break;
                    case $prop->getType()->getName() === "string":
                        preg_match('/^char\[(\d*|0x[0-9a-f]+|\$this->[a-zA-Z_][a-zA-Z0-9-_]*)\]$/', $arg['type'], $matches);
                        if (!$matches || count($matches) !== 2) {
                            throw new \Exception(sprintf(
                                "Invalid type %s on unpacking %s::%s",
                                $arg['type'],
                                static::class,
                                $propName,
                            ));
                        }

                        $size = $matches[1];

                        if ($size === "") {
                            if (!isset($arg['size'])) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (no size specified)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                ));
                            }
                            $length = $arg['size'];
                            if (is_string($length)) {
                                $ast = ConditionParse::parse($length);
                                $length = executeAST($ast, [
                                    '$this' => $this,
                                ]);
                            }
                            if (!is_int($length)) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (size is not int)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                ));
                            }
                        } else if (str_starts_with($size, '$this->')) {
                            $typePropName = substr($size, 7);
                            if (!isset($this->$typePropName)) {
                                throw new \Exception(sprintf(
                                    "Invalid type %s on unpacking %s::%s (property %s not found)",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                    $typePropName,
                                ));
                            }
                            $length = $this->$typePropName;
                        } else {
                            $length = intval($size, 0);
                        }

                        if ($cursor + $length > strlen($remaining)) {
                            // var_dump($cursor, $length, strlen($remaining));
                            throw new \Exception(sprintf(
                                "Not enough data on unpacking %s::%s",
                                static::class,
                                $propName,
                            ));
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
                        switch ($arg['type']) {
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
                                    "Invalid type %s on unpacking %s::%s",
                                    $arg['type'],
                                    static::class,
                                    $propName,
                                ));
                        }

                        if ($cursor + $size > strlen($remaining)) {
                            throw new \Exception(sprintf(
                                "Not enough data on unpacking %s::%s",
                                static::class,
                                $propName,
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
                            "Invalid type %s on unpacking %s::%s",
                            $arg['type'],
                            static::class,
                            $propName,
                        ));
                }

                // var_dump($propName, $value);
                $prop->setValue($this, $value);
            }
        }

        // $this->raw = substr($remaining, 0, $cursor);

        return $cursor;
    }

    public function pack(): string
    {
        $packItems = static::getPackItems();

        $packArgs = "";
        $values = [];

        foreach ($packItems as $packInfos) {
            foreach ($packInfos as [$arg, $prop]) {
                if (isset($arg['cond'])) {
                    $useThis = true;
                    foreach ($arg['cond'] as $cond) {
                        $ast = ConditionParse::parse($cond);
                        $condValue = executeAST($ast, [
                            '$this' => $this,
                        ]);
                        // var_dump($condValue, $ast, $cond, $arg['cond']);
                        if (!$condValue) {
                            $useThis = false;
                            break;
                        }
                    }
                    if (!$useThis) {
                        continue;
                    }
                }

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
                        switch ($arg['type']) {
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
                                    $arg['type'],
                                    static::class,
                                    $prop->getName(),
                                ));
                        }
                        break;
                    default:
                        throw new \Exception(sprintf(
                            "Invalid type %s on packing %s::%s",
                            $arg['type'],
                            static::class,
                            $prop->getName(),
                        ));
                }
                $values[] = $value;
            }
        }
        return pack($packArgs, ...$values);
    }
}
