<?php

declare(strict_types=1);

namespace PE;

require_once __DIR__ . '/../utilFunctions.php';

use Unpacker\NullVerifier;
use Unpacker\CommonPack;
use Unpacker\Unpacker;
use Unpacker\PackItem;
use PE\VSFixedFileInfo;
use PE\VarFileInfo;

/**
 * VS_VERSIONINFO
 */
class VSVersionInfo implements CommonPack
{

    public const SIGNATURE = "VS_VERSION_INFO\0";

    #[PackItem(offset: 0x00, type: 'uint16')]
    public int $length;
    #[PackItem(offset: 0x02, type: 'uint16')]
    public int $valueLength;
    #[PackItem(offset: 0x04, type: 'uint16')]
    public int $type;
    #[PackItem(offset: 0x06, type: 'char[32]')]
    public string $key;
    public ?string $padding1;
    public VSFixedFileInfo|VarFileInfo $value;
    // our VSFixedFileInfo always aligned to 4 bytes, so this member always donot exist
    // public ?string $padding2;

    /** @var StringFileInfo[] */
    public array $children;

    use Unpacker {
        pack as _pack;
        unpack as _unpack;
    }
    use NullVerifier {
        verify as _verify;
    }

    public function __construct(
        public int $offset = 0,
    ) {}

    public function unpack(string $remaining): int
    {
        // headers
        $parsed = $this->_unpack($remaining);
        if ($this->length < 38) {
            throw new \Exception("failed to unpack headers");
        }

        // padding1
        $padding1Length = paddingLength($this->offset + $parsed, 4);
        if ($padding1Length !== 0) {
            // needs padding1
            $this->padding1 = substr($remaining, $parsed, $padding1Length);
        } else {
            $this->padding1 = null;
        }
        $parsed += $padding1Length;

        // value
        $this->value = new VSFixedFileInfo();
        $parsed += $this->value->unpack(substr($remaining, $parsed));

        // children
        $this->children = [];
        while ($parsed < $this->length) {
            $sig = substr($remaining, $parsed + 6, 6);
            switch ($sig) {
                case "S\0t\0r\0":
                    // printf("StringFileInfo at 0x%x\n", $parsed);
                    $child = new StringFileInfo($parsed);
                    break;
                case "V\0a\0r\0":
                    // printf("VarFileInfo at 0x%x\n", $parsed);
                    $child = new VarFileInfo($parsed);
                    break;
                default:
                    throw new \Exception("unknown signature: " . $sig);
            }
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
        $this->length = 38 /* 6(header) + 32(key) */;

        $paddingLength = paddingLength($this->offset + $this->length, 4);
        if ($paddingLength !== 0) {
            $this->padding1 = str_repeat("\0", $paddingLength);
        } else {
            $this->padding1 = null;
        }
        $this->length += $paddingLength;
        $this->length += 0x34; // value length

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
            $this->padding1 .
            $this->value->pack() .
            $data;
    }

    public function verify(): void
    {
        if (utf16le2utf8($this->key) !== static::SIGNATURE) {
            throw new \Exception("invalid signature");
        }
        $this->value->verify();
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
        $ret->type = 0;
        $ret->valueLength = 0x34;
        $ret->key = utf82utf16le(static::SIGNATURE);
        $ret->children = [];
        return $ret;
    }
}
