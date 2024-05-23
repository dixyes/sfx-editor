<?php

declare(strict_types=1);

namespace Unpacker;

use PHPUnit\Framework\TestCase;

class TestPackCond implements CommonPack
{
    use Unpacker {
        checkCond as public;
    }
    use NullVerifier;

    public string $str;

    public int $int1;

    public int $int2;
}

class UnpackerTest extends TestCase
{
    public function testCheckCond(): void
    {
        $item = new TestPackCond();
        $testCases = [
            // ['true', true],
            // ['true ', true],
            // [' true', true],
            // [' false ', false],
            // ['0', false],
            // ['1', true],
            // ['-1', true],
            // ['0x1', true],
            // ['0xff', true],
            // ['0xffffffff', true],
            // ['0x7fffffffffffffff', true],
            // ['0b1', true],
            // ['0d99', true],
            // ['0d00', false],
            // ['0o775', true],
            // ['0o0', false],
            ['"true"', true],
            ['"false"', true],
            ['"0"', true],
            ['""', false],
            ['"\""', true],
            ['"\"1"', true],
            ["''", false],
            ["'\"'", true],
            ["'\\''", true],
            ["'\\\\'", true],
            ['"\x01"', true],
            ['"\u26a0"', true],
            ['"\U0001f956"', true],
            ['"\0"', true],
            ['"\02"', true],
            ['"\023"', true],
            ['"\'"', true],
            ['"\\\""', true],
            ['"\\\""', true],
        ];

        foreach ($testCases as $testCase) {
            $cond = $testCase[0];
            // $this->assertEquals($item->checkCond([$cond]),$testCase[1]);
            $item->checkCond([$cond]);
        }

        $item->str = "str";
        $item->int1 = 1;
        $item->int2 = 2;
    }
}