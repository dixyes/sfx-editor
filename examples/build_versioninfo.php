<?php

require_once __DIR__ . '/../sfx-editor/autoload.php';

use PE\String_;
use PE\StringTable;
use PE\StringFileInfo;
use PE\Var_;
use PE\VarFileInfo;
use PE\VSFixedFileInfo;
use PE\VSVersionInfo;

$vvi = VSVersionInfo::create();

$vffi = new VSFixedFileInfo();
$vvi->value = $vffi;
$vffi->signature = VSFixedFileInfo::SIGNATURE;
$vffi->strucVersion = 0x10000;
$vffi->fileVersionMS = 0x000a0000;
$vffi->fileVersionLS = 0x65f40e28;
$vffi->productVersionMS = 0x000a0000;
$vffi->productVersionLS = 0x65f40e28;
$vffi->fileFlagsMask = 63;
$vffi->fileFlags = 0;
$vffi->fileOS = VSFixedFileInfo::VOS__WINDOWS32 | VSFixedFileInfo::VOS_NT;
$vffi->fileType = VSFixedFileInfo::VFT_APP;
$vffi->fileSubtype = 0;
$vffi->fileDateMS = 0;
$vffi->fileDateLS = 0;

$sfi = StringFileInfo::create();
$vvi->children[] = $sfi;

$st = StringTable::createStringTable("040904B0");
$sfi->children[] = $st;
$stvars = [
    "CompanyName" => "Microsoft Corporation",
    "FileDescription" => "Notepad",
    "FileVersion" => "10.0.26100.3624 (WinBuild.160101.0800)",
    "InternalName" => "Notepad",
    "LegalCopyright" => "© Microsoft Corporation. All rights reserved.",
    "OriginalFilename" => "NOTEPAD.EXE",
    "ProductName" => "Microsoft® Windows® Operating System",
    "ProductVersion" => "10.0.26100.3624",
];
foreach ($stvars as $key => $value) {
    $str = String_::createText($key . "\0", $value . "\0");
    $st->children[] = $str;
}

$vfi = VarFileInfo::create();
$vvi->children[] = $vfi;
$var = Var_::create([0x04b00409]);
$vfi->children[] = $var;

file_put_contents(__DIR__ . '/notepad_rsrc_section.bin', $vvi->pack());
