<?php

declare(strict_types=1);

namespace Unpacker;

require_once(__DIR__ . '/../../src/Unpacker/utilFunctions.php');

use PHPUnit\Framework\TestCase;

class FunctionTest extends TestCase
{
    public function setUp(): void
    {
        error_reporting(E_ALL);
    }

    public function testParseString(): void
    {
        $testCases = [
            // [buf, literal, value] or
            // [buf, except]

            // empty
            ['""', '""', ''],
            ["''", "''", ''],
            // extra things
            ["'' ", "''", ''],
            ["'' ", "''", ''],
            ["'' '", "''", ''],
            ['"" "', '""', ''],
            // nested quote
            ['"\'"', '"\'"', "'"],
            ["'\"'", "'\"'", '"'],
            // double quote escape
            ['"\'"', '"\'"', "'"],
            ['"\\\\"', '"\\\\"', "\\"],
            ['"\""', '"\""', '"'],
            ['"\\\'"', '"\\\'"', "'"],
            ['"\\\\\\\'"', '"\\\\\\\'"', "\\'"],
            ['"\\"\\\\"', '"\\"\\\\"', '"\\'],
            ['"\0"', '"\0"', "\0"],
            ['"\02"', '"\02"', "\02"],
            ['"\023"', '"\023"', "\023"],
            ['"\\x63"', '"\\x63"', 'c'],
            ['"\\u26a0"', '"\\u26a0"', '⚠'],
            ['"\\U0001f956"', '"\\U0001f956"', '🥖'],
            ['"\\x0a"', '"\\x0a"', "\n"],
            ['"\\x0A"', '"\\x0A"', "\n"],
            ['"\\n"', '"\\n"', "\n"],
            ['"\\Q"', '"\\Q"', 'Q'],
            // single quote escape
            ["'\\\\'", "'\\\\'", "\\"],
            ["'\''", "'\''", "'"],
            ["'\\n'", "'\\n'", '\n'],
            // bad cases
            ["", "not a string", null],
            ["cafebabe", "not a string", null],
            ["'", "unterminated string", null],
            ['"', 'unterminated string', null],
            ["'cafebabe", "unterminated string", null],
            ['"deadbeef\\', 'unterminated string', null],
            ['"\\"', 'unterminated string', null],
            ["'\\'", 'unterminated string', null],
            ['"\\x0"', 'invalid hex/unicode escape sequence at', null],
            ['"\\xun"', 'invalid hex/unicode escape sequence at', null],
            ['"\\u"', 'invalid hex/unicode escape sequence at', null],
            ['"\\u1"', 'invalid hex/unicode escape sequence at', null],
            ['"\\u12"', 'invalid hex/unicode escape sequence at', null],
            ['"\\u123"', 'invalid hex/unicode escape sequence at', null],
            ['"\\u123x"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U0"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U01"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U012"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U0123"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U01234"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U012345"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U0123456"', 'invalid hex/unicode escape sequence at', null],
            ['"\\U0123456x"', 'invalid hex/unicode escape sequence at', null],
        ];

        foreach ($testCases as $testCase) {
            [$input, $literalOrException, $expectValue] = $testCase;
            // var_dump($testCase);
            if ($expectValue === null) {
                try {
                    [
                        'literal' => $literal,
                        'value' => $value,
                        'remaining' => $remaining,
                    ] = parseString($input);
                    $this->fail("expected exception, but got $literal, $value, $remaining");
                } catch (\Exception $e) {
                    $this->assertStringStartsWith($literalOrException, $e->getMessage());
                }
            } else {
                [
                    'literal' => $literal,
                    'value' => $value,
                    'remaining' => $remaining,
                ] = parseString($input);
                // var_dump($literal, $value, $remaining);
                $this->assertEquals($literalOrException, $literal);
                $this->assertEquals($expectValue, $value);
                $this->assertEquals(substr($input, strlen($literal)), $remaining);
            }
        }
    }
    public function testTokenizeCond(): void
    {
        $testCases = [
            // [buf, tokens] or
            // [buf, except]
            ["", []],
            [" ", []],
            ["\t", []],
            ["\n", []],
            ["\r", []],
            ['$this->', [['type' => '$this->'],]],
            ['$this ->', "unexpected character"],
            ['$data[0] == 1', [
                ['type' => '$data'],
                ['type' => '['],
                ['type' => 'number', 'literal' => '0', 'value' => 0],
                ['type' => ']'],
                ['type' => '=='],
                ['type' => 'number', 'literal' => '1', 'value' => 1],
            ]],
            ['$rem[1+2- 3*0x1] != !false', [
                ['type' => '$rem'],
                ['type' => '['],
                ['type' => 'number', 'literal' => '1', 'value' => 1],
                ['type' => '+'],
                ['type' => 'number', 'literal' => '2', 'value' => 2],
                ['type' => '-'],
                ['type' => 'number', 'literal' => '3', 'value' => 3],
                ['type' => '*'],
                ['type' => 'number', 'literal' => '0x1', 'value' => 1],
                ['type' => ']'],
                ['type' => '!='],
                ['type' => '!'],
                ['type' => 'false'],
            ]],
            ['$off - true == ~ (0b10 | 0o721 & 0d00)', [
                ['type' => '$off'],
                ['type' => '-'],
                ['type' => 'true'],
                ['type' => '=='],
                ['type' => '~'],
                ['type' => '('],
                ['type' => 'number', 'literal' => '0b10', 'value' => 2],
                ['type' => '|'],
                ['type' => 'number', 'literal' => '0o721', 'value' => 465],
                ['type' => '&'],
                ['type' => 'number', 'literal' => '0d00', 'value' => 0],
                ['type' => ')'],
            ]],
            ['"cafebabe" ^\'deadbeef\' / 123 % 055 <= ident', [
                ['type' => 'string', 'literal' => '"cafebabe"', 'value' => 'cafebabe'],
                ['type' => '^'],
                ['type' => 'string', 'literal' => "'deadbeef'", 'value' => 'deadbeef'],
                ['type' => '/'],
                ['type' => 'number', 'literal' => '123', 'value' => 123],
                ['type' => '%'],
                ['type' => 'number', 'literal' => '055', 'value' => 45],
                ['type' => '<='],
                ['type' => 'identifier', 'literal' => 'ident'],
            ]],
            ['0d00 || 0721 | 0b111 & 0x42 ^ ~12', [
                ['type' => 'number', 'literal' => '0d00', 'value' => 0],
                ['type' => '||'],
                ['type' => 'number', 'literal' => '0721', 'value' => 465],
                ['type' => '|'],
                ['type' => 'number', 'literal' => '0b111', 'value' => 7],
                ['type' => '&'],
                ['type' => 'number', 'literal' => '0x42', 'value' => 0x42],
                ['type' => '^'],
                ['type' => '~'],
                ['type' => 'number', 'literal' => '12', 'value' => 12],
            ]],
            ['0xNaN', "invalid number at "],
            ['0b11111111111111111111111111111111111111111111111111111111111111110', "exceeds php int size as binary number at"],
            ['0xffffffffffffffff0', "exceeds php int size as hexadecimal number at "],
            ['0o17777777777777777777770', "exceeds php int size as octal number at"],
            ['0d184467440737095516150', "exceeds php int size as decimal number at"],
            ['0x7fffffffffffffff', [
                ['type' => 'number', 'literal' => '0x7fffffffffffffff', 'value' => 0x7fffffffffffffff],
            ]],
            ['0x8fffffffffffffff', "exceeds php int size as hexadecimal number at "],
            ['0x8000000000000000', "exceeds php int size as hexadecimal number at "],
            ['0o2000000000000000000000', "exceeds php int size as octal number at "],
            ['0d18446744073709551616', "exceeds php int size as decimal number at "],
            ['?', "unexpected character "],
            ['"?"', [
                ['type' => 'string', 'literal' => '"?"', 'value' => '?'],
            ]],
        ];

        foreach ($testCases as $testCase) {
            [$input, $tokenOrExcept] = $testCase;
            if (is_array($tokenOrExcept)) {
                $tokens = tokenizeCond($input);
                // print_r($tokens);
                // print_r($tokenOrExcept);
                $this->assertEquals($tokenOrExcept, $tokens);
            } else {
                try {
                    $tokens = tokenizeCond($input);
                    $this->fail("expected exception, but got " . json_encode($tokens));
                } catch (\Exception $e) {
                    $this->assertStringStartsWith($tokenOrExcept, $e->getMessage());
                }
            }
        }
    }

    public function testExecuteAST(): void
    {
        $context = [
            '$intvar' => 42,
            '$strvar' => 'abcd',
            '$arr' => [
                'some' => 'efgh',
            ],
            '$this' => new class{
                public int $flags = 0x1000;
            },
        ];

        $testCases = [
            // [ast, result, except]
            [['val', null], null, null],
            [['val', 1], 1, null],
            [['var', '$intvar'], 42, null],
            [['var', '$notexistvar'], null, "variable not found"],
            [['prop', 'flags'], 0x1000, null],
            [['prop', 'notexist'], null, '$this or property not found'],
            [['[]', ['var', '$strvar'], ['val', 1]], 'b', null],
            [['[]', ['var', '$arr'], ['val', 'some']], 'efgh', null],
            [['!', ['val', false]], true, null],
            [['!', ['val', "cafebabe"]], false, null],
            [['!', ['var', '$intvar']], false, null],
            [['~', ['var', '$intvar']], ~42, null],
            [['~', ['val', '$intvar']], null, 'bitwise not ~ can only apply to int'],
            [['*', ['val', 6], ['val', 7]], 42, null],
            [['/', ['val', 6], ['val', 7]], 0, null],
            [['%', ['val', 6], ['val', 7]], 6 % 7, null],
            [['+', ['val', 6], ['val', 7]], 6 + 7, null],
            [['-', ['val', 6], ['val', 7]], 6 - 7, null],
            [['<<', ['val', 6], ['val', 7]], 6 << 7, null],
            [['>>', ['val', 6], ['val', 7]], 6 >> 7, null],
            [['==', ['val', 6], ['val', 7]], 6 === 7, null],
            [['==', ['val', 6], ['val', '6']], 6 === '6', null],
            [['==', ['val', 6], ['val', '7']], 6 === '7', null],
            [['==', ['val', '6'], ['val', '6']], '6' === '6', null],
            [['!=', ['val', 6], ['val', 7]], 6 !== 7, null],
            [['!=', ['val', 6], ['val', '6']], 6 !== '6', null],
            [['!=', ['val', 6], ['val', '7']], 6 !== '7', null],
            [['!=', ['val', '6'], ['val', '6']], '6' !== '6', null],
            [['<', ['val', 6], ['val', 7]], 6 < 7, null],
            [['<=', ['val', 6], ['val', 7]], 6 <= 7, null],
            [['>', ['val', 6], ['val', 7]], 6 > 7, null],
            [['>=', ['val', 6], ['val', 7]], 6 >= 7, null],
            [['&', ['val', 6], ['val', 7]], 6 & 7, null],
            [['&', ['val', '6'], ['val', 7]], null, 'bitwise and & left can only be int'],
            [['&', ['val', 6], ['val', '7']], null, 'bitwise and & right can only be int'],
            [['|', ['val', 6], ['val', 7]], 6 | 7, null],
            [['|', ['val', '6'], ['val', 7]], null, 'bitwise or | left can only be int'],
            [['|', ['val', 6], ['val', '7']], null, 'bitwise or | right can only be int'],
            [['^', ['val', 6], ['val', 7]], 6 ^ 7, null],
            [['^', ['val', '6'], ['val', 7]], null, 'bitwise xor ^ left can only be int'],
            [['^', ['val', 6], ['val', '7']], null, 'bitwise xor ^ right can only be int'],
            [['&&', ['val', true], ['val', false]], false, null],
            [['&&', ['val', true], ['val', true]], true, null],
            [['&&', ['val', false], ['val', true]], false, null],
            [['&&', ['val', false], ['val', false]], false, null],
            [['||', ['val', true], ['val', false]], true, null],
            [['||', ['val', true], ['val', true]], true, null],
            [['||', ['val', false], ['val', true]], true, null],
            [['||', ['val', false], ['val', false]], false, null],
            [['&&', ['val', true], ['val', 1]], 1, null],
            [['&&', ['val', 1], ['val', 2]], 2, null],
            [['&&', ['val', 0], ['val', true]], 0, null],
            [['&&', ['val', false], ['val', 0]], false, null],
            [['||', ['val', true], ['val', 1]], true, null],
            [['||', ['val', 1], ['val', 2]], 1, null],
            [['||', ['val', 0], ['val', true]], true, null],
            [['||', ['val', false], ['val', 0]], 0, null],
            // question mark like syntax "a && 1 || 2"
            [['||', ['&&', ['val', 0], ['val', 1]], ['val', 2]], 2, null],
            [['||', ['&&', ['val', true], ['val', 1]], ['val', 2]], 1, null], 
        ];

        foreach ($testCases as $testCase) {
            [$ast, $result, $except] = $testCase;
            if ($except !== null) {
                try {
                    $ret = executeAST($ast, $context);
                    $this->fail("expected exception, but got $ret");
                } catch (\Exception $e) {
                    $this->assertStringStartsWith($except, $e->getMessage());
                }
            } else {
                $ret = executeAST($ast, $context);
                $this->assertEquals($result, $ret, "Failed for " . json_encode($ast));
            }
        }
    }
}
