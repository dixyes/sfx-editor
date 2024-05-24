<?php

declare(strict_types=1);

namespace Unpacker;

use PHPUnit\Framework\TestCase;

class TestPackCommonUse implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[4]')]
    public string $str1;

    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $u32;

    #[PackItem(offset: 0x08, type: 'uint64')]
    public int $u64;

    #[PackItem(offset: 0x10, type: 'uint16')]
    public int $u16;

    #[PackItem(offset: 0x12, type: 'uint8')]
    public int $u8;

    #[PackItem(offset: 0x13, type: 'char[13]')]
    public string $str2;

    #[PackItem(offset: 0x20, type: 'char[$this->u32]')]
    public string $strend;
}

class TestPackItem implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    public function __construct(
        int $u32 = 0,
        int $u64 = 0,
    ) {
        $this->u32 = $u32;
        $this->u64 = $u64;
    }

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $u32;

    #[PackItem(offset: 0x04, type: 'uint64')]
    public int $u64;
}

class TestPackArray implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $count;

    #[PackItem(offset: 0x04, type: 'TestPackItem[$this->count]')]
    public array $array;
}

class TestPackItemArg implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    public function __construct(
        public string $type,
    ) {
    }

    #[PackItem(offset: 0x00, type: 'uint64')]
    public int $u64;

    public function setU64(int $u64): static
    {
        $this->u64 = $u64;
        return $this;
    }
}

class TestPackArrayArg implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $count;

    #[PackItem(offset: 0x04, type: 'TestPackItemArg[$this->count]', args: ['type' => 'a'])]
    public array $array;
}

class TestArrayNotationOnNotArray implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'TestPackItem[1]')]
    public int $array;
}

class TestNoSizeOnArray implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'TestPackItem[]')]
    public array $array;
}

class TestSizeNotFoundOnArray implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'TestPackItem[$this->notexist]')]
    public array $array;
}

class TestCharArrNotaionOnNotString implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[1]')]
    public array $array;
}

class TestSizeNotFoundOnString implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[]')]
    public array $array;
}

class TestSizeNotFoundOnString2 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'char[$this->notexist]')]
    public string $str;
}

class TestIntOnNotInt implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public string $str;
}

class TestIntOnNotInt2 implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public array $array;
}

class TestUnknownType implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    #[PackItem(offset: 0x00, type: 'unknowntype!')]
    public int $int;
}

/**
 * MachO Header
 */
class TestConditionalParse implements CommonPack
{
    use Unpacker;
    use NullVerifier;

    const CPU_TYPE_X86 = 0x07;
    const CPU_TYPE_X86_64 = 0x01000007;
    const CPU_TYPE_ARM = 0x0C;
    const CPU_TYPE_ARM64 = 0x0100000C;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $magic;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cpuType;
    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $cpuSubtype;
    #[PackItem(offset: 0x0C, type: 'uint32')]
    public int $fileType;
    #[PackItem(offset: 0x10, type: 'uint32')]
    public int $nCmds;
    #[PackItem(offset: 0x14, type: 'uint32')]
    public int $sizeOfCmds;
    #[PackItem(offset: 0x18, type: 'uint32')]
    public int $flags;
    #[PackItem(offset: 0x1C, type: 'uint32', cond: ['$this->cpuType & 0x01000000'])]
    public int $reserved;

    #[PackItem(offset: 0x1c, type: 'uint32', cond: ['!($this->cpuType & 0x01000000)'])]
    #[PackItem(offset: 0x20, type: 'uint32', cond: ['$this->cpuType & 0x01000000'])]
    public int $loadCommand; // fake
}

class UnpackerTest extends TestCase
{
    public function setUp(): void
    {
        error_reporting(E_ALL);
    }

    public function testUnpackCommonUse(): void
    {
        // common use
        $testCases = [
            // [data, expect] or
            // [data, except]
            [
                "str1\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00\x08\x09\x0a\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c1",
                [
                    'str1' => 'str1',
                    'u32' => 1,
                    'u64' => 0x07060504030201,
                    'u16' => 0x0908,
                    'u8' => 0x0a,
                    'str2' => "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c",
                    'strend' => '1',
                ],
                33,
            ],
            [
                "str2\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00\x08\x09\x0a\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c1",
                [
                    'str1' => 'str2',
                    'u32' => 0,
                    'u64' => 0x07060504030201,
                    'u16' => 0x0908,
                    'u8' => 0x0a,
                    'str2' => "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c",
                    'strend' => '',
                ],
                32,
            ],
            [
                "str3\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00",
                "Not enough data on unpacking",
                0
            ]
        ];

        $this->checkTestCases($testCases, TestPackCommonUse::class);
    }

    public function testUnpackArray(): void
    {
        // array
        $testCases = [
            // [data, expect] or
            // [data, except]
            [
                "\x02\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00\x02\x00\x00\x00\x01\x02\x03\x04\x05\x06\x06\x00",
                [
                    'count' => 2,
                    'array' => [
                        new TestPackItem(
                            u32: 1,
                            u64: 0x07060504030201,
                        ),
                        new TestPackItem(
                            u32: 2,
                            u64: 0x06060504030201,
                        ),
                    ],
                ],
                28,
            ],
            [
                "\x01\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00123",
                [
                    'count' => 1,
                    'array' => [
                        new TestPackItem(
                            u32: 1,
                            u64: 0x07060504030201,
                        ),
                    ],
                ],
                16,
            ],
            [
                "\x00\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00123",
                [
                    'count' => 0,
                    'array' => [],
                ],
                4,
            ],
        ];

        $this->checkTestCases($testCases, TestPackArray::class);
    }

    public function testUnpackArrayArg(): void
    {
        // array
        $testCases = [
            // [data, expect] or
            // [data, except]
            [
                "\x02\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00\x02\x00\x00\x00\x01\x02\x03\x04\x05\x06\x06\x00",
                [
                    'count' => 2,
                    'array' => [
                        (new TestPackItemArg('a'))->setU64(0x0403020100000001),
                        (new TestPackItemArg('a'))->setU64(0x0200070605),
                    ],
                ],
                20,
            ],
            [
                "\x01\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00123",
                [
                    'count' => 1,
                    'array' => [
                        (new TestPackItemArg('a'))->setU64(0x0403020100000001),
                    ],
                ],
                12,
            ],
            [
                "\x00\x00\x00\x00\x01\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x00123",
                [
                    'count' => 0,
                    'array' => [],
                ],
                4,
            ],
        ];

        $this->checkTestCases($testCases, TestPackArrayArg::class);
    }

    public function testConditionalParse(): void
    {
        $testCases = [
            // [data, expect] or
            // [data, except]
            [
                "magi\x07\x00\x00\x00cupsFTYPncmdSCMDflagLOADextra",
                [
                    'magic' => 0x6967616d,
                    'cpuType' => TestConditionalParse::CPU_TYPE_X86,
                    'cpuSubtype' => 0x73707563,
                    'fileType' => 0x50595446,
                    'nCmds' => 0x646d636e,
                    'sizeOfCmds' => 0x444d4353,
                    'flags' => 0x67616c66,
                    'loadCommand' => 0x44414f4c,
                ],
                0x20,
            ],
            [
                "magi\x07\x00\x00\x01cupsFTYPncmdSCMDflagRESVloadExtra",
                [
                    'magic' => 0x6967616d,
                    'cpuType' => TestConditionalParse::CPU_TYPE_X86_64,
                    'cpuSubtype' => 0x73707563,
                    'fileType' => 0x50595446,
                    'nCmds' => 0x646d636e,
                    'sizeOfCmds' => 0x444d4353,
                    'flags' => 0x67616c66,
                    'reserved' => 0x56534552,
                    'loadCommand' => 0x64616f6c,
                ],
                0x24,
            ],
        ];

        $this->checkTestCases($testCases, TestConditionalParse::class);
    }

    private function checkTestCases(array $testCases, string $className): void
    {
        foreach ($testCases as $i => [$data, $expect, $length]) {
            if (is_array($expect)) {
                $unpacker = new $className();
                $parsed = $unpacker->unpack($data);
                var_dump(bin2hex($data));
                $this->assertEquals($length, $parsed, "Checking $i with parsed length");
                foreach ($expect as $key => $value) {
                    $this->assertEquals($value, $unpacker->$key, "Checking $i with $key");
                }

                $packed = $unpacker->pack();
                $this->assertEquals(substr($data, 0, $length), $packed, "Checking $i with packed data");
            } else {
                try {
                    $unpacker = new $className();
                    $parsed = $unpacker->unpack($data);
                    $this->fail("Expect exception for $data: " . json_encode($unpacker->pack()));
                } catch (\Exception $e) {
                    $this->assertStringStartsWith($expect, $e->getMessage());
                }
            }
        }
    }

    public function testBadNotations(): void
    {
        $data = 'Whatever';
        $testCases = [
            // [className, expect]
            [TestArrayNotationOnNotArray::class, 'Invalid type'],
            [TestNoSizeOnArray::class, 'Invalid type'],
            [TestSizeNotFoundOnArray::class, 'Invalid type'],
            [TestCharArrNotaionOnNotString::class, 'Class "\Unpacker\char" does not exist'],
            [TestSizeNotFoundOnString::class, 'Class "\Unpacker\char" does not exist'],
            [TestSizeNotFoundOnString2::class, 'Invalid type'],
            [TestIntOnNotInt::class, 'Invalid type'],
            [TestIntOnNotInt2::class, 'Invalid property type'],
            [TestUnknownType::class, 'Invalid type'],
        ];

        foreach ($testCases as [$className, $expect]) {
            try {
                $unpacker = new $className();
                $parsed = $unpacker->unpack($data);
                $this->fail("Expect exception for $data: " . json_encode($unpacker->pack()));
            } catch (\Exception $e) {
                $this->assertStringStartsWith($expect, $e->getMessage(), "Checking $className");
            }
        }
    }
}
