<?php

declare(strict_types=1);

namespace PE;

require_once __DIR__ . '/../utilFunctions.php';

use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;

/**
 * Var
 */
class Var_ implements CommonPack
{
    const SIGNATURE = "Translation\0";

    #[PackItem(offset: 0x00, type: 'uint16')]
    public int $length;
    /**
     * length of the value in bytes
     */
    #[PackItem(offset: 0x02, type: 'uint16')]
    public int $valueLength;
    /**
     * 1 for (utf16le) text, 0 for binary
     */
    #[PackItem(offset: 0x04, type: 'uint16')]
    public int $type;
    #[PackItem(offset: 0x06, type: 'char[24]')]
    public string $key;
    public ?string $padding;
    /**
     * dword representing lang id
     */
    public array $values;

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
        if ($this->length < 30) {
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

        // value
        if (($this->length - $parsed) % 4 !== 0) {
            throw new \Exception("invalid struct length");
        }
        if ($this->valueLength !== $this->length - $parsed) {
            throw new \Exception("invalid value length");
        }
        $this->values = unpack('V*', substr($remaining, $parsed, $this->valueLength));
        $parsed += $this->valueLength;

        return $parsed;
    }

    public function pack(): string
    {
        $this->valueLength = count($this->values) * 4;
        $this->length = 6 + strlen($this->key);

        $paddingLength = paddingLength($this->offset + $this->length, 4);
        if ($paddingLength !== 0) {
            $this->padding = str_repeat("\0", $paddingLength);
        } else {
            $this->padding = null;
        }
        $this->length += $paddingLength + $this->valueLength;

        return $this->_pack() . $this->padding . pack('V*', ...$this->values);
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

    /**
     * @param array<int> $values
     */
    static public function create(array $values): static
    {
        $ret = new static(0);
        $ret->type = 0;
        $ret->key = utf82utf16le(static::SIGNATURE);
        $ret->values = $values;
        return $ret;
    }
}
