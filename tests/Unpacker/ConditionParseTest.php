<?php

declare(strict_types=1);

namespace Unpacker;

use PHPUnit\Framework\TestCase;

class ConditionParseTest extends TestCase
{
    public function setUp(): void
    {
        error_reporting(E_ALL);
    }

    public function testParse(): void
    {
        $testCases = [
            // [cond, ast] or
            // [cond, exception]
            ["true", ['val', true],],
            ['$data[1]', ["[]", ['var', '$data'], ['val', 1]]],
            ['$this->flags & 0x1000', ["&", ['prop', 'flags'], ['val', 0x1000]]],
        ];

        foreach ($testCases as [$cond, $astOrException]) {
            if (is_array($astOrException)) {
                $ast = ConditionParse::parse($cond);
                print_r($ast);
                print_r($astOrException);
                $this->assertEquals($astOrException, $ast);
            } else {
                try {
                    $ast = ConditionParse::parse($cond);
                    $this->fail("Expect exception for $cond: " . json_encode($ast));
                } catch (\Exception $e) {
                    $this->assertStringStartsWith($astOrException, $e->getMessage());
                }
            }
        }
    }
}