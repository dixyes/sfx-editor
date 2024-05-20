<?php

function _utf16le2utf8(string $wcs): string
{
    $mbs = '';

    $charArr = unpack('v*', $wcs);
    $len = count($charArr);
    for ($i = 1; $i < $len + 1; $i++) {
        $char = $charArr[$i];
        if ($char < 0x80) {
            $mbs .= chr($char);
        } else if ($char < 0x800) {
            $mbs .= chr(0xc0 | ($char >> 6));
            $mbs .= chr(0x80 | ($char & 0x3f));
        } else if ($char < 0x8000) {
            $mbs .= chr(0xe0 | ($char >> 12));
            $mbs .= chr(0x80 | (($char >> 6) & 0x3f));
            $mbs .= chr(0x80 | ($char & 0x3f));
        } else {
            // bigger than 2 bytes
            // at write time only U+10FFFF is available, so assume 3 bytes
            $unicode = 0;
            if ($i >= $len) {
                // invalid
                throw new \Exception('Invalid UTF-16LE');
            }
            $char2 = $charArr[++$i];
            $unicode = ($char - 0xD800) * 0x400 + $char2 - 0xDC00 + 0x10000;

            $mbs .= chr(0xf0 | ($unicode >> 18));
            $mbs .= chr(0x80 | (($unicode >> 12) & 0x3f));
            $mbs .= chr(0x80 | (($unicode >> 6) & 0x3f));
            $mbs .= chr(0x80 | ($unicode & 0x3f));
        }
    }
    return $mbs;
}

function utf16le2utf8(string $wcs): string
{
    if (extension_loaded('mbstring')) {
        return mb_convert_encoding($wcs, 'UTF-8', 'UTF-16LE');
    } else if (extension_loaded('iconv')) {
        return iconv('UTF-16LE', 'UTF-8', $wcs);
    } else if (extension_loaded('ffi') && PHP_OS_FAMILY === 'Windows') {
        static $ffi = \FFI::cdef("int WideCharToMultiByte(
            unsigned int CodePage,
            unsigned long dwFlags,
            const char *lpWideCharStr,
            int cchWideChar,
            char *lpMultiByteStr,
            int cbMultiByte,
            const char *lpDefaultChar,
            int *lpUsedDefaultChar
        );", 'kernel32.dll');
        $len = $ffi->WideCharToMultiByte(65001, 0, $wcs, strlen($wcs), null, 0, null, null);
        $ret = \FFI::new("char[$len]");
        $len = $ffi->WideCharToMultiByte(65001, 0, $wcs, strlen($wcs), $ret, $len, null, null);
        $ret = \FFI::string($ret, $len);

        return $ret;
    } else {
        return _utf16le2utf8($wcs);
    }
}

function _utf82utf16le(string $mbs): string
{
    $wcs = '';
    $len = strlen($mbs);

    for ($i = 0; $i < $len; $i++) {
        $char = ord($mbs[$i]);
        if ($char < 0x80) {
            $wcs .= pack('v', $char);
        } else if (($char & 0xe0) === 0xc0) {
            $char2 = ord($mbs[++$i]);
            $wcs .= pack('v', (($char & 0x1f) << 6) | ($char2 & 0x3f));
        } else if (($char & 0xf0) === 0xe0) {
            $char2 = ord($mbs[++$i]);
            $char3 = ord($mbs[++$i]);
            $unicode = (($char & 0x0f) << 12) | (($char2 & 0x3f) << 6) | ($char3 & 0x3f);
            $wcs .= pack('v', $unicode);
        } else if (($char & 0xf8) === 0xf0) {
            $char2 = ord($mbs[++$i]);
            $char3 = ord($mbs[++$i]);
            $char4 = ord($mbs[++$i]);
            $unicode = (($char & 0x07) << 18) | (($char2 & 0x3f) << 12) | (($char3 & 0x3f) << 6) | ($char4 & 0x3f);
            $unicode -= 0x10000;
            $high = 0xD800 | ($unicode >> 10);
            $low = 0xDC00 | ($unicode & 0x3ff);
            $wcs .= pack('vv', $high, $low);
        }
    }
    return $wcs;
}

function utf82utf16le(string $mbs): string
{
    if (extension_loaded('mbstring')) {
        return mb_convert_encoding($mbs, 'UTF-16LE', 'UTF-8');
    } else if (extension_loaded('iconv')) {
        return iconv('UTF-8', 'UTF-16LE', $mbs);
    } else if (extension_loaded('ffi') && PHP_OS_FAMILY === 'Windows') {
        static $ffi = \FFI::cdef("int MultiByteToWideChar(
            unsigned int CodePage,
            unsigned long dwFlags,
            const char *lpMultiByteStr,
            int cbMultiByte,
            char *lpWideCharStr,
            int cchWideChar
        );uint32_t GetLastError();
        ", 'kernel32.dll');
        $len = $ffi->MultiByteToWideChar(65001, 0, $mbs, strlen($mbs), null, 0);
        $ret = \FFI::new("char[" . (string)($len * 2) . "]");
        $len = $ffi->MultiByteToWideChar(65001, 0, $mbs, strlen($mbs), $ret, $len);
        $ret = \FFI::string($ret, $len*2);

        return $ret;
    } else {
        return _utf82utf16le($mbs);
    }
}
