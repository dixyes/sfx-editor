<?php

declare(strict_types=1);

namespace PE;

require_once __DIR__ . '/../utilFunctions.php';

use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;

/**
 * String
 */
class String_ implements CommonPack
{
    #[PackItem(offset: 0x00, type: 'uint16')]
    public int $length;
    /**
     * length of the value in words (uint16)
     */
    #[PackItem(offset: 0x02, type: 'uint16')]
    public int $valueLength;
    /**
     * 1 for (utf16le) text, 0 for binary
     */
    #[PackItem(offset: 0x04, type: 'uint16')]
    public int $type;
    /**
     * nul terminated wide string
     */
    public string $key;
    public ?string $padding;
    /**
     * nul terminated wide string
     */
    public string $value;
    public ?string $padding2;

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
        if ($this->length < 6) {
            throw new \Exception("failed to unpack headers");
        }

        // key
        $key = null;
        for ($wi = 0; $wi < $this->length / 2; $wi++) {
            if (substr($remaining, $parsed + ($wi * 2), 2) === "\0\0") {
                $strLen = 2 + ($wi * 2);
                $key = substr($remaining, $parsed, $strLen);
                $parsed += $strLen;
                break;
            }
        }
        if ($key === null) {
            throw new \Exception("failed to unpack key");
        }
        $this->key = $key;

        // padding
        $paddingLength = paddingLength($this->offset + $parsed, 4);
        if ($paddingLength !== 0) {
            // needs padding
            $this->padding = substr($remaining, $parsed, $paddingLength);
        } else {
            $this->padding = null;
        }
        $parsed += $paddingLength;

        // value
        $this->value = substr($remaining, $parsed, $this->valueLength * 2);
        if (strlen($this->value) !== $this->valueLength * 2) {
            throw new \Exception("failed to unpack value");
        }
        $parsed += $this->valueLength * 2;

        // padding2
        $padding2Length = paddingLength($this->offset + $this->length, 4);
        if ($padding2Length !== 0) {
            // needs padding2 (not belong to the struct, but this make it easier to unpack)
            $this->padding2 = substr($remaining, $parsed, $padding2Length);
        } else {
            $this->padding2 = null;
        }
        $parsed += $padding2Length;

        return $parsed;
    }

    public function pack(): string
    {
        $this->valueLength = strlen($this->value) / 2;
        $this->length = 6 + strlen($this->key);

        $paddingLength = paddingLength($this->offset + $this->length, 4);
        if ($paddingLength !== 0) {
            $this->padding = str_repeat("\0", $paddingLength);
        } else {
            $this->padding = null;
        }
        $this->length += $paddingLength + strlen($this->value);

        $padding2Length = paddingLength($this->offset + $this->length, 4);
        if ($padding2Length !== 0) {
            $this->padding2 = str_repeat("\0", $padding2Length);
        } else {
            $this->padding2 = null;
        }

        return $this->_pack() . $this->key . $this->padding . $this->value . $this->padding2;
    }

    public function verify(): void
    {
        $this->_verify();
        if ($this->type !== 1 && $this->type !== 0) {
            throw new \Exception("invalid type");
        }
    }

    public function resum(int $offset): void
    {
        $this->offset = $offset;
    }

    static public function createText(string $mbKey, string $mbValue): static
    {
        $ret = new static(0);
        $ret->type = 1;
        $ret->key = utf82utf16le($mbKey);
        $ret->value = utf82utf16le($mbValue);
        return $ret;
    }
}
