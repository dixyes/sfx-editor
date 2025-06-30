<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

/**
 * VS_FIXEDFILEINFO
 */
class VSFixedFileInfo implements CommonPack
{

    public const VS_FF_DEBUG = 0x00000001;
    public const VS_FF_PRERELEASE = 0x00000002;
    public const VS_FF_PATCHED = 0x00000004;
    public const VS_FF_PRIVATEBUILD = 0x00000008;
    public const VS_FF_INFOINFERRED = 0x00000010;
    public const VS_FF_SPECIALBUILD = 0x00000020;

    public const VOS_UNKNOWN = 0x00000000;
    public const VOS__WINDOWS16 = 0x00000001;
    public const VOS__PM16 = 0x00000002;
    public const VOS__PM32 = 0x00000003;
    public const VOS__WINDOWS32 = 0x00000004;
    public const VOS_DOS = 0x00010000;
    public const VOS_OS216 = 0x00020000;
    public const VOS_OS232 = 0x00030000;
    public const VOS_NT = 0x00040000;

    public const VFT_UNKNOWN = 0x00000000;
    public const VFT_APP = 0x00000001;
    public const VFT_DLL = 0x00000002;
    public const VFT_DRV = 0x00000003;
    public const VFT_FONT = 0x00000004;
    public const VFT_VXD = 0x00000005;
    public const VFT_STATIC_LIB = 0x00000007;

    public const VFT2_UNKNOWN = 0x00000000;
    public const VFT2_DRV_PRINTER = 0x00000001;
    public const VFT2_DRV_KEYBOARD = 0x00000002;
    public const VFT2_DRV_LANGUAGE = 0x00000003;
    public const VFT2_DRV_DISPLAY = 0x00000004;
    public const VFT2_DRV_MOUSE = 0x00000005;
    public const VFT2_DRV_NETWORK = 0x00000006;
    public const VFT2_DRV_SYSTEM = 0x00000007;
    public const VFT2_DRV_INSTALLABLE = 0x00000008;
    public const VFT2_DRV_SOUND = 0x00000009;
    public const VFT2_DRV_COMM = 0x0000000A;
    public const VFT2_DRV_VERSIONED_PRINTER = 0x0000000C;
    public const VFT2_FONT_RASTER = 0x00000001;
    public const VFT2_FONT_VECTOR = 0x00000002;
    public const VFT2_FONT_TRUETYPE = 0x00000003;

    public const SIGNATURE = 0xFEEF04BD;

    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $signature;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $strucVersion;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $fileVersionMS;
    #[PackItem(offset: 0x0c, type: 'uint32')]
    public int $fileVersionLS;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $productVersionMS;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $productVersionLS;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $fileFlagsMask;
    #[PackItem(offset: 0x1c, type: 'uint32')]
    public int $fileFlags;
    #[PackItem(offset: 0x20, type: 'uint32')]
    public int $fileOS;
    #[PackItem(offset: 0x24, type: 'uint32')]
    public int $fileType;
    #[PackItem(offset: 0x28, type: 'uint32')]
    public int $fileSubtype;
    #[PackItem(offset: 0x2c, type: 'uint32')]
    public int $fileDateMS;
    #[PackItem(offset: 0x30, type: 'uint32')]
    public int $fileDateLS;

    public function verify(): void
    {
        if ($this->strucVersion !== 0x10000) {
            throw new \Exception("unsupported struct version {$this->strucVersion}");
        }
        if ($this->signature !== static::SIGNATURE) {
            throw new \Exception("unsupported sign {$this->signature}");
        }
    }
}
