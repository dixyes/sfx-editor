<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;

/**
 * @note this class should not be used directly, use RSRC to parse RSRC section
 */
class ResourceDirectory implements CommonPack
{
    use Unpacker;

    #[PackItem(offset: 0x00, type: 'uint32')]
    public int $characteristics;
    #[PackItem(offset: 0x04, type: 'uint32')]
    public int $timeDateStamp;
    #[PackItem(offset: 0x08, type: 'uint16')]
    public int $majorVersion;
    #[PackItem(offset: 0x0A, type: 'uint16')]
    public int $minorVersion;
    #[PackItem(offset: 0x0C, type: 'uint16')]
    public int $numberOfNamedEntries;
    #[PackItem(offset: 0x0E, type: 'uint16')]
    public int $numberOfIdEntries;

    // debug only
    // public int $offset;

    /** @var ResourceDirectoryEntry[] */
    public array $entries;


    public function verify(): void
    {
        if ($this->characteristics !== 0) {
            throw new \Exception('Invalid resource directory characteristics');
        }
    }

    /**
     * resum
     *
     * @param integer $offset not used for ResourceDirectory
     * @return void
     */
    public function resum(int $offset): void
    {
        $this->numberOfIdEntries = 0;
        $this->numberOfNamedEntries = 0;
        foreach ($this->entries as $entry) {
            if ($entry->nameOrId & 0x80000000) {
                $this->numberOfNamedEntries++;
            } else {
                $this->numberOfIdEntries++;
            }
        }
    }
}
