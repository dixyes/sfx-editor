<?php

declare(strict_types=1);

require_once(__DIR__ . '/../src/utilFunctions.php');

use PHPUnit\Framework\TestCase;

class FunctionTest extends TestCase
{
    public function testConvertFunction(): void
    {
        // test utf82utf16le and utf16le2utf8
        $testCases = [
            "\xe4\xbd\xa0\xe5\xa5\xbd\xe4\xb8\x96\xe7\x95\x8c" => "`O}Y\x16NLu",
            'cafebabe' => "c\x00a\x00f\x00e\x00b\x00a\x00b\x00e\x00",
            "\xe2\x9a\xa0" => "\xa0&",
            "\xf0\x9f\xa5\x96" => ">\xd8V\xdd",
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, utf82utf16le($input));
        }
        foreach ($testCases as $expected => $input) {
            $this->assertEquals($expected, utf16le2utf8($input));
        }

        // test unicode2utf8
        $testCases = [
            0x4f60 => "\xe4\xbd\xa0",
            0x63 => "c",
            0x26a0 => "\xe2\x9a\xa0",
            0x1f956 => "\xf0\x9f\xa5\x96",
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, unicode2utf8($input));
        }
    }
}
