<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

abstract class LoadCommand implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    const LC_SEGMENT = 0x01;
    const LC_SYMTAB = 0x02;
    const LC_DYSYMTAB = 0x0B;
    const LC_ID_DYLINKER = 0x0E;
    const LC_LOAD_DYLINKER = 0x0F;
    const LC_SEGMENT_64 = 0x19;
    const LC_UUID = 0x1B;
    const LC_CODE_SIGNATURE = 0x1D;
    const LC_SEGMENT_SPLIT_INFO = 0x1E;
    const LC_DYLD_INFO = 0x22;
    const LC_DYLD_INFO_ONLY = 0x80000022;
    const LC_FUNCTION_STARTS = 0x26;
    const LC_DYLD_ENVIRONMENT = 0x27;
    const LC_DATA_IN_CODE = 0x29;
    const LC_MAIN = 0x80000028;
    const LC_SOURCE_VERSION = 0x2a;
    const LC_DYLIB_CODE_SIGN_DRS = 0x2B;
    const LC_LINKER_OPTIMIZATION_HINT = 0x2E;
    const LC_BUILD_VERSION = 0x32;
    const LC_DYLD_EXPORTS_TRIE = 0x80000033;
    const LC_DYLD_CHAINED_FIXUPS = 0x80000034;

    public int $cmd;
    public int $cmdSize;

    static function fromData(string $data): static
    {
        $type = unpack('V', $data)[1];
        switch ($type) {
            case self::LC_SEGMENT:
                $cmd = new SegmentCommand32();
                break;
            case self::LC_SYMTAB:
                $cmd = new SymbolTableCommand();
                break;
            case self::LC_DYSYMTAB:
                $cmd = new DynamicSymbolTableCommand();
                break;
            case self::LC_ID_DYLINKER:
            case self::LC_LOAD_DYLINKER:
            case self::LC_DYLD_ENVIRONMENT:
                $cmd = new DynamicLinkerCommand();
                break;
            case self::LC_SEGMENT_64:
                $cmd = new SegmentCommand64();
                break;
            case self::LC_UUID:
                $cmd = new UUIDCommand();
                break;
            case self::LC_CODE_SIGNATURE:
            case self::LC_SEGMENT_SPLIT_INFO:
            case self::LC_FUNCTION_STARTS:
            case self::LC_DATA_IN_CODE:
            case self::LC_DYLIB_CODE_SIGN_DRS:
            case self::LC_LINKER_OPTIMIZATION_HINT:
            case self::LC_DYLD_EXPORTS_TRIE:
            case self::LC_DYLD_CHAINED_FIXUPS:
                $cmd = new LinkeditDataCommand();
                break;
            case self::LC_DYLD_INFO:
            case self::LC_DYLD_INFO_ONLY:
                $cmd = new DYLDInfoCommand();
                break;
            case self::LC_MAIN:
                $cmd = new EntrypointCommand();
                break;
            case self::LC_SOURCE_VERSION:
                $cmd = new SourceVersionCommand();
                break;
            case self::LC_BUILD_VERSION:
                $cmd = new BuildVersionCommand();
                break;
            default:
                $cmd = new DummyCommand();
                break;
        }
        $cmd->unpack($data);
        return $cmd;
    }
}
