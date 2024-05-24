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
            ['0d00 || 0721 | 0b111 & 0x42 ^ ~12',
                ["||",
                    ["val",0],
                    ["^",
                        ["&",
                            ["|",
                                ["val", 0721],
                                ["val", 0b111],
                            ],
                            ["val", 0x42],
                        ],
                        ["~",
                            ["val", 12],
                        ],
                    ],
                ],
            ],
            ['0x1 && 0x2 || 0x3',
                ['||',
                    ['&&',
                        ['val', 1],
                        ['val', 2],
                    ],
                    ['val', 3],
                ],
            ],
            ['0x1 || 0x2 && 0x3',
                ['&&',
                    ['||',
                        ['val', 1],
                        ['val', 2],
                    ],
                    ['val', 3],
                ],
            ],
        ];

        foreach ($testCases as [$cond, $astOrException]) {
            if (is_array($astOrException)) {
                $ast = ConditionParse::parse($cond);
                // printf('%s', json_encode($ast, JSON_PRETTY_PRINT));
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