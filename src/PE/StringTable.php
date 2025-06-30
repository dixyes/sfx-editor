<?php

declare(strict_types=1);

namespace PE;

require_once __DIR__ . '/../utilFunctions.php';

use Unpacker\NullVerifier;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;

/**
 * StringTable
 */
class StringTable implements CommonPack
{
    #[PackItem(offset: 0x00, type: 'uint16')]
    public int $length;
    /**
     * always 0
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

        // children
        $this->children = [];
        while ($parsed < $this->length) {
            // printf("String_ at 0x%x\n", $parsed);
            $child = new String_($parsed);
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
            $this->key .
            $this->padding .
            $data;
    }

    public function verify(): void
    {
        foreach ($this->children as $child) {
            $child->verify();
        }
        if ($this->type !== 1 && $this->type !== 0) {
            throw new \Exception("invalid type");
        }
        if ($this->valueLength !== 0) {
            throw new \Exception("invalid valueLength");
        }
    }

    public function resum(int $offset): void
    {
        $this->offset = $offset;
    }

    static public function createStringTable(string $mbKey): static
    {
        $ret = new static(0);
        $ret->type = 1;
        $ret->valueLength = 0;
        $ret->key = utf82utf16le($mbKey);
        $ret->children = [];
        return $ret;
    }
}
