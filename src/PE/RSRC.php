<?php

declare(strict_types=1);

namespace PE;

use Unpacker\Unpacker;
use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class RSRC implements CommonPack
{
    use NullVerifier;

    /** @var array<ResourceDirectory> */
    public array $dirs;
    /** @var array<ResourceDirectoryEntry> */
    public array $entries;
    /** @var array<ResourceDataEntry> */
    public array $dataDirs;

    public string $padding;

    private int $maxReached = 0;

    public function __construct(
        public int $baseRVA
    ) {
    }

    private function unpackDir(string $rsrc, int $offset): ResourceDirectory
    {
        $dir = new ResourceDirectory();
        $this->dirs[] = $dir;
        // $dir->offset = $offset;
        $offset += $dir->unpack(substr($rsrc, $offset));
        $this->maxReached = max($this->maxReached, $offset);
        for ($i = 0; $i < $dir->numberOfNamedEntries + $dir->numberOfIdEntries; $i++) {
            $entry = new ResourceDirectoryEntry();
            $this->entries[] = $entry;
            $dir->entries[] = $entry;
            // $entry->offset = $offset;
            $offset += $entry->unpack(substr($rsrc, $offset));
            $this->maxReached = max($this->maxReached, $offset);
            if ($entry->offsetToData & 0x80000000) {
                // this is directory
                $entry->item = $this->unpackDir($rsrc, $entry->offsetToData & 0x7FFFFFFF);
            } else {
                $entry->item = new ResourceDataEntry();
                $this->dataDirs[] = $entry->item;
                // $entry->item->offset = $entry->offsetToData;
                $entry->item->unpack(substr($rsrc, $entry->offsetToData));
                $entry->item->data = substr(
                    $rsrc,
                    ($entry->item->dataRVA - $this->baseRVA),
                    $entry->item->size,
                );
                $this->maxReached = max(
                    $this->maxReached,
                    ($entry->item->dataRVA - $this->baseRVA) + $entry->item->size,
                );
            }
            if ($entry->nameOrId & 0x80000000) {
                $nameLen = unpack('v', substr($rsrc, $entry->nameOrId & 0x7FFFFFFF, 2))[1];
                $entry->name = substr($rsrc, ($entry->nameOrId & 0x7FFFFFFF) + 2, $nameLen * 2);
            }
        }
        return $dir;
    }

    public function unpack(string $remaining): int
    {
        $rsrc = $remaining;
        $this->unpackDir($rsrc, 0);
        // padding to 512 bytes
        $this->padding = substr($remaining, $this->maxReached, $this->maxReached % 512);
        return $this->maxReached + (512 - ($this->maxReached % 512));
    }

    public function pack(): string
    {
        /*
         * | root | lv1 dir ... (order by entry id) | lv2 dir ... | data dir ... | name string ... | data ... |
         */
        // reorder
        $dirs = [$this->dirs[0]];
        $dirOffset = 0;
        $dirOffsets = [];

        $dataDirs = [];
        for ($i = 0; $i < count($dirs); $i++) {
            $dir = $dirs[$i];
            $dir->resum(0);
            $dirOffsets[] = $dirOffset;
            $dirOffset += 16 + $dir->numberOfNamedEntries * 8 + $dir->numberOfIdEntries * 8;
            usort($dir->entries, fn ($a, $b) => $a->nameOrId - $b->nameOrId);
            foreach ($dir->entries as $entry) {
                if ($entry->item instanceof ResourceDirectory && !in_array($entry->item, $dirs)) {
                    $dirs[] = $entry->item;
                } else if ($entry->item instanceof ResourceDataEntry && !in_array($entry->item, $dataDirs)) {
                    $dataDirs[] = $entry->item;
                }
            }
            if (count($dirs) > count($this->dirs)) {
                throw new \Exception('Invalid directory structure');
            }
        }
        // assert count($dirs) === count($this->dirs)
        $rsrc = '';

        foreach ($dirs as $dir) {
            $rsrc .= $dir->pack();
            foreach ($dir->entries as $entry) {
                if ($entry->item instanceof ResourceDirectory) {
                    $entry->offsetToData = 0x80000000 | $dirOffsets[array_search($entry->item, $dirs)];
                } else {
                    $entry->offsetToData = $dirOffset + array_search($entry->item, $dataDirs) * 16;
                }
                $rsrc .= $entry->pack();
            }
        }

        $dataOffset = strlen($rsrc) + count($dataDirs) * 16;
        foreach ($dirs as $dir) {
            foreach ($dir->entries as $entry) {
                if ($entry->nameOrId & 0x80000000) {
                    $dataOffset += 2 + strlen($entry->name);
                }
            }
        }

        $data = '';
        foreach ($dataDirs as $dataDir) {
            $dataDir->dataRVA = $this->baseRVA + $dataOffset;
            $dataDir->size = strlen($dataDir->data);
            $rsrc .= $dataDir->pack();
            $dataOffset += $dataDir->size;
            $data .= $dataDir->data;
            if ($dataOffset % 4 !== 0) {
                $paddingLen = 4 - ($dataOffset % 4);
                $dataOffset += $paddingLen;
                $data .= str_repeat("\0", $paddingLen);
            }
        }

        foreach ($dirs as $dir) {
            foreach ($dir->entries as $entry) {
                if ($entry->nameOrId & 0x80000000) {
                    $name = $entry->name;
                    $rsrc .= pack('v', strlen($name) / 2) . $name;
                }
            }
        }

        $rsrc .= $data;

        if (strlen($rsrc) % 512 !== 0) {
            $rsrc .= str_repeat("\0", 512 - (strlen($rsrc) % 512));
        }

        return $rsrc;
    }
}
