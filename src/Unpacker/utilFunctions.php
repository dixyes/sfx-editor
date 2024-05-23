<?php

declare(strict_types=1);

namespace Unpacker;

require_once __DIR__ . '/../utilFunctions.php';

function parseString(string $buf): array
{
    if ($buf === '' || ($buf[0] !== '"' && $buf[0] !== "'")) {
        throw new \Exception("not a string");
    }
    $i = 0;
    $quote = $buf[$i];

    $escapeState = 0;
    $value = "";
    $unicodeCode = 0;

    $i = 1;
    for (; $i < strlen($buf); $i++) {
        $ch = $buf[$i];
        $chInt = ord($ch);
        if ($quote === "'") {
            // only \' and \\ is escape seq in single quote
            $escapeState = 0;
            $next2 = substr($buf, $i, 2);
            if (in_array($next2, ["\\'", "\\\\"])) {
                $i++;
                $ch = $next2[1];
                // unacceptable state
                $escapeState = 1;
            } else if ($ch === "'") {
                // string end
                break;
            }
            $value .= $ch;
            continue;
        }
        // else double quote
        // printf("escapeState: %d ch: %02x %s\n", $escapeState, ord($ch), $ch);
        switch ($escapeState) {
            case 0:
                if ($ch === '\\') {
                    $escapeState = 1;
                } else if ($ch === $quote) {
                    // string end
                    break 2;
                } else {
                    $value .= $ch;
                }
                break;
            case 1:
                if ($ch === 'x') {
                    $escapeState = 8;
                } else if ($ch === 'u') {
                    $escapeState = 6;
                } else if ($ch === 'U') {
                    $escapeState = 2;
                } else if ($chInt >= 0x30 && $chInt <= 0x37) {
                    $escapeState = 10;
                    $unicodeCode = intval($ch, 8);
                } else {
                    if ($ch === $quote && $i === strlen($buf) - 1) {
                        // string end
                        break 2;
                    }
                    $value .= stripcslashes("\\$ch");
                    $escapeState = 0;
                }
                break;
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
                if (ctype_xdigit($ch)) {
                    $unicodeCode = $unicodeCode * 16 + intval($ch, 16);
                    $escapeState++;
                } else {
                    throw new \Exception(sprintf(
                        "invalid hex/unicode escape sequence at %d",
                        $i,
                    ));
                }
                if ($escapeState == 10) {
                    $value .= unicode2utf8($unicodeCode);
                    $escapeState = 0;
                    $unicodeCode = 0;
                }
                break;
            case 10:
                if ($chInt >= 0x30 && $chInt <= 0x37) {
                    $escapeState++;
                    $unicodeCode = $unicodeCode * 8 + intval($ch, 8);
                } else {
                    $value .= unicode2utf8($unicodeCode);
                    $escapeState = 0;
                    $unicodeCode = 0;
                    // roll back
                    $i--;
                }
                break;
            case 11:
                if ($chInt >= 0x30 && $chInt <= 0x37) {
                    $unicodeCode = $unicodeCode * 8 + intval($ch, 8);
                    $value .= unicode2utf8($unicodeCode);
                } else {
                    $value .= unicode2utf8($unicodeCode);
                    // roll back
                    $i--;
                }
                $escapeState = 0;
                $unicodeCode = 0;
                break;
            default:
                throw new \Exception(sprintf(
                    "invalid state when parsing string at %d (impossible)",
                    $i,
                ));
        }
    }

    if ($i >= strlen($buf) || $buf[$i] !== $quote || $escapeState !== 0) {
        throw new \Exception("unterminated string");
    }

    return [
        'value' => $value,
        'literal' => substr($buf, 0, $i + 1),
        'remaining' => substr($buf, $i + 1),
    ];
}

function tokenizeCond(string $cond): array
{
    $tokens = [];
    for ($i = 0; $i < strlen($cond); $i++) {
        $char = $cond[$i];
        $next7 = substr($cond, $i, 7);
        if ($next7 === '$this->') {
            $tokens[] = [
                'type' => $next7,
            ];
            $i += 6;
            continue;
        }
        $next5 = substr($cond, $i, 5);
        if (in_array($next5, ['$data', 'false'])) {
            $tokens[] = [
                'type' => $next5,
            ];
            $i += 4;
            continue;
        }
        $next4 = substr($cond, $i, 4);
        if (in_array($next4, ['$rem', '$off', 'true'])) {
            $tokens[] = [
                'type' => $next4,
            ];
            $i += 3;
            continue;
        }
        $next2 = substr($cond, $i, 2);
        $int = 0;
        switch ($next2) {
            case '0b':
                for ($len = 2; $i + $len < strlen($cond); $len++) {
                    $char = $cond[$i + $len];
                    if ($char !== '0' && $char !== '1') {
                        break;
                    }
                    $newDigit = intval($char, 2);
                    if (
                        $int > PHP_INT_MAX >> 1 ||
                        PHP_INT_MAX - ($int << 1) < $newDigit
                    ) {
                        // exceed php int size
                        throw new \Exception(sprintf(
                            "exceeds php int size as binary number at %d",
                            $cond,
                            $i,
                        ));
                    }
                    $int = ($int << 1) + $newDigit;
                }
                goto num;
            case '0o':
                for ($len = 2; $i + $len < strlen($cond); $len++) {
                    $char = $cond[$i + $len];
                    $ord = ord($char);
                    if ($ord < 0x30 || $ord > 0x37) {
                        break;
                    }
                    $newDigit = intval($char, 8);
                    if (
                        $int > PHP_INT_MAX >> 3 ||
                        PHP_INT_MAX - ($int << 3) < $newDigit
                    ) {
                        // exceed php int size
                        throw new \Exception(sprintf(
                            "exceeds php int size as octal number at %d",
                            $cond,
                            $i,
                        ));
                    }
                    $int = ($int << 3) + $newDigit;
                }
                goto num;
            case '0d':
                for ($len = 2; $i + $len < strlen($cond); $len++) {
                    $char = $cond[$i + $len];
                    if (!ctype_digit($char)) {
                        break;
                    }
                    $newDigit = intval($char, 10);
                    if (
                        $int > PHP_INT_MAX / 10 ||
                        PHP_INT_MAX - $int * 10 < $newDigit
                    ) {
                        // exceed php int size
                        throw new \Exception(sprintf(
                            "exceeds php int size as decimal number at %d",
                            $cond,
                            $i,
                        ));
                    }
                    $int = $int * 10 + $newDigit;
                }
            case '0x':
                for ($len = 2; $i + $len < strlen($cond); $len++) {
                    $char = $cond[$i + $len];
                    if (!ctype_xdigit($char)) {
                        break;
                    }
                    $newDigit = intval($char, 16);
                    if (
                        $int > PHP_INT_MAX >> 4 ||
                        PHP_INT_MAX - ($int << 4) < $newDigit
                    ) {
                        // exceed php int size
                        throw new \Exception(sprintf(
                            "exceeds php int size as hexadecimal number at %d",
                            $cond,
                            $i,
                        ));
                    }
                    $int = ($int << 4) + $newDigit;
                }
                num:
                if ($len === 2) {
                    throw new \Exception(sprintf(
                        "invalid number at %d",
                        $cond,
                        $i,
                    ));
                }
                $literal = substr($cond, $i, $len);
                $tokens[] = [
                    'type' => 'number',
                    'literal' => $literal,
                    'value' => $int,
                ];
                $i += $len - 1;
                continue 2;
            case '>=':
            case '<=':
            case '!=':
            case '==':
                $tokens[] = [
                    'type' => $next2
                ];
                $i += 1;
                continue 2;
        }
        switch (true) {
            case in_array($char, [
                '(', ')', '[', ']', '+', '-', '*', '/', '%', '>', '<', '|', '&', '^', '!', '~'
            ]):
                $tokens[] = [
                    'type' => $char
                ];
                break;
            case ctype_space($char):
                break;
            case $char === '"':
            case $char === "'":
                $parsed = parseString(substr($cond, $i));
                $tokens[] = [
                    'type' => 'string',
                    'literal' => $parsed['literal'],
                    'value' => $parsed['value'],
                ];
                $i += strlen($parsed['literal']) - 1;
                break;
            case ctype_alpha($char) || $char === '_':
                $len = 0;
                while ($i + $len < strlen($cond) && (ctype_alnum($cond[$i + $len]) || $cond[$i + $len] === '_')) {
                    $len += 1;
                }
                $literal = substr($cond, $i, $len);
                $tokens[] = [
                    'type' => 'identifier',
                    'literal' => $literal,
                ];
                $i += $len - 1;
                break;
            case ctype_digit($char):
                $len = 0;
                while ($i + $len < strlen($cond) && ctype_digit($cond[$i + $len])) {
                    $len += 1;
                }
                $literal = substr($cond, $i, $len);
                $tokens[] = [
                    'type' => 'number',
                    'literal' => $literal,
                    'value' => intval($literal, $literal[0] === '0' ? 8 : 10),
                ];
                $i += $len - 1;
                break;
            default:
                throw new \Exception(sprintf(
                    "unexpected character %s at %d",
                    $char,
                    $i,
                ));
        }
    }
    return $tokens;
}
