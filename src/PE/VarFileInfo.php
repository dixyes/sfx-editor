<?php

declare(strict_types=1);

namespace PE;

require_once __DIR__ . '/../utilFunctions.php';

use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use PE\Var_;

/**
 * VarFileInfo
 */
class VarFileInfo implements CommonPack
{
    public const SIGNATURE = "VarFileInfo\0";

    /**
     * length of the struct in bytes
     */
    #[PackItem(offset: 0x00, type: 'uint16')]
    public int $length;
    /**
     * always 0
     */
    #[PackItem(offset: 0x02, type: 'uint16')]
    public int $valueLength;
    #[PackItem(offset: 0x04, type: 'uint16')]
    public int $type;
    #[PackItem(offset: 0x06, type: 'char[24]')]
    public string $key;
    public ?string $padding;
    /** @var StringTable[] */
    public array $children;

    use Unpacker {
        pack as _pack;
        unpack as _unpack;
    }
    use NullVerifier {
        verify as _verify;
        resum as resum;
    }

    public function __construct(
        public int $offset,
    ) {}


    public function unpack(string $remaining): int
    {
        // headers
        $parsed = $this->_unpack($remaining);
        if ($this->length < 36) {
            throw new \Exception("failed to unpack headers");
        }
        if (utf16le2utf8($this->key) !== static::SIGNATURE) {
            throw new \Exception("invalid signature");
        }

        // padding
        $paddingLength = paddingLength($this->offset + $parsed, 4);
        if ($paddingLength !== 0) {
            // needs padding
            $this->padding = substr($remaining, $parsed, $paddingLength);
        } else {
            $this->padding = null;
        }
        $parsed += $paddingLength;

        // children
        $this->children = [];
        while ($parsed < $this->length) {
            // printf("Var_ at 0x%x\n", $parsed);
            $child = new Var_($parsed);
            $i = $child->unpack(substr($remaining, $parsed));
            if ($i === 0) {
                throw new \Exception("failed to unpack child");
            }
            $parsed += $i;
            $this->children[] = $child;
        }
        $paddingLength = paddingLength($this->offset + $this->length, 4);
        assert($this->length + $paddingLength === $parsed);

        return $parsed;
    }

    public function pack(): string
    {
        $this->length = 6 + strlen($this->key);

        $paddingLength = paddingLength($this->offset + $this->length, 4);
        if ($paddingLength !== 0) {
            $this->padding = str_repeat("\0", $paddingLength);
        } else {
            $this->padding = null;
        }
        $this->length += $paddingLength;

        $offset = 0;
        $data = '';
        foreach ($this->children as $child) {
            $offset = strlen($data);
            $child->resum($offset);
            $data .= $child->pack();
        }
        // length here is without ending padding
        // so we use the last child's length
        $this->length += $offset + $child->length;

        return $this->_pack() .
            $this->padding .
            $data;
    }

    public function verify(): void
    {
        if (utf16le2utf8($this->key) !== static::SIGNATURE) {
            throw new \Exception("invalid signature");
        }
        foreach ($this->children as $child) {
            $child->verify();
        }
    }

    public function resum(int $offset): void
    {
        $this->offset = $offset;
    }

    static public function create(): static
    {
        $ret = new static(0);
        $ret->type = 1;
        $ret->valueLength = 0;
        $ret->key = utf82utf16le(static::SIGNATURE);
        $ret->children = [];
        return $ret;
    }
}
