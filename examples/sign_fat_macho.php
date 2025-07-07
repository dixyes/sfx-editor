<?php

require_once __DIR__ . '/../autoload.php';

$keyname = "Apple Development: user@example.com (abcdefghij)";

// before this, make a signed fat mach-o first:
// 1. (optional) strip: `strip micro.sfx.arm64` `strip micro.sfx.x86_64`
// 2. create fat mach-o: `lipo -create micro.sfx.arm64 micro.sfx.x86_64 -output micro.sfx.universal` (or use make_fat_macho.php)
// 3. sign (this is in-place): `codesign -s "Apple Development: user@example.com (abcdefghij)" micro.sfx.universal`

// read your payload, here we use a simple php script
$payload = "<?php echo 'hello from ' . php_uname('m') . PHP_EOL ;";

// unpack signed fat mach-o
$fat = new \MachO\FatMachO();
$fat->unpack(file_get_contents('micro.sfx.universal.signed') . $payload);

// wrap payload and sign the stub (which holds the payload)
$fat->wrapPayload(false, function (\MachO\MachOFile $stub) use ($keyname) {
    file_put_contents('stub', $stub->pack());
    $ret = passthru('codesign -s "' . $keyname . '" ./stub', $rc);
    if ($ret !== null || $rc !== 0) {
        throw new \Exception("Failed to sign stub");
    }
    $ret = new \MachO\MachOFile();
    $ret->unpack(file_get_contents('stub'));
    return $ret;
});

// repack fat mach-o
file_put_contents('wrapped.signed', $fat->pack());
chmod('wrapped.signed', 0755);

// validate the signature
passthru('codesign -v wrapped.signed');
