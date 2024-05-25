<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;

class DynamicLinkerCommand extends LoadCommand
{
    /** @var int $cmd LC_ID_DYLINKER 0x0E or LC_LOAD_DYLINKER 0x0F or LC_DYLD_ENVIRONMENT 0x27 */
    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $cmd;
    /** @var int $cmdSize 0x18 */
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $cmdSize;

    #[PackItem(offset: 0x08, type: 'uint32')]
    public int $offset;

    public string $name;

    public function unpack(string $remaining): int
    {
        parent::unpack($remaining);
        if ($this->offset > $this->cmdSize) {
            throw new \Exception('Invalid offset');
        }

        $this->name = substr($remaining, $this->offset, $this->cmdSize - $this->offset);

        return $this->cmdSize;
    }

    public function pack(): string
    {
        $this->cmdSize = 0x0c + strlen($this->name);

        // mach-o/loader.h :
        // Once again any padded bytes to bring the cmdsize field to a multiple
        // of 4 bytes must be zero.
        if ($this->cmdSize % 4 !== 0) {
            $paddingSize = 4 - ($this->cmdSize % 4);
            $this->cmdSize += $paddingSize;
            $this->name .= str_repeat("\0", $paddingSize);
        }
        $this->offset = 0x0c;

        return parent::pack() . $this->name;
    }
}
