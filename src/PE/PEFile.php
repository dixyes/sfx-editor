<?php

declare(strict_types=1);

namespace PE;

use Unpacker\CommonPack;

class PEFile implements CommonPack
{

    public DOSHeader $dosHeader;
    public DOSStub $dosStub;
    public COFFHeader $coffHeader;
    public COFFOptHeader32|COFFOptHeader64 $coffOptHeader;
    /** @var SectionHeader[] $sectionHeaders */
    public array $sectionHeaders;

    public ?string $attributeCertificate;
    public ?string $payload;

    public function unpack(string $remaining): int
    {
        $ret = 0;
        $file = $remaining;

        $dosHeader = new DOSHeader();
        $consume = $dosHeader->unpack($remaining);
        $ret += $consume;
        $dosHeader->verify();
        $this->dosHeader = $dosHeader;

        $remaining = substr($remaining, $consume);

        $dosStub = new DOSStub(size: $dosHeader->peHeaderOffset - (16 * $dosHeader->headerSize));
        $consume = $dosStub->unpack($remaining);
        $ret += $consume;
        $dosStub->verify();
        $this->dosStub = $dosStub;

        $remaining = substr($remaining, $consume);

        $coffHeader = new COFFHeader();
        $consume = $coffHeader->unpack($remaining);
        $ret += $consume;
        $coffHeader->verify();
        $this->coffHeader = $coffHeader;

        $remaining = substr($remaining, $consume);

        $coffOptHeader = match (substr($remaining, 0, 2)) {
            "\x0b\x01" /* 0x10b */ => new COFFOptHeader32(),
            "\x0b\x02" /* 0x20b */ => new COFFOptHeader64(),
            default => throw new \Exception('Unknown optional header magic: ' . bin2hex(substr($remaining, 0, 2))),
        };
        $consume = $coffOptHeader->unpack($remaining);
        $ret += $consume;
        $coffOptHeader->verify();
        $this->coffOptHeader = $coffOptHeader;
        if ($coffOptHeader->dataDirectories[4]->size > 0) {
            $this->attributeCertificate = substr($file, $coffOptHeader->dataDirectories[4]->virtualAddress, $coffOptHeader->dataDirectories[4]->size);
        } else {
            $this->attributeCertificate = null;
        }

        $remaining = substr($remaining, $consume);

        /** @var SectionHeader[] $sectionHeaders */
        $sectionHeaders = [];
        $sectionOffset = $ret + (0x28 * $coffHeader->numberOfSections);
        if ($sectionOffset % $coffOptHeader->fileAlignment !== 0) {
            $sectionOffset += $coffOptHeader->fileAlignment - ($sectionOffset % $coffOptHeader->fileAlignment);
        }
        for ($i = 0; $i < $coffHeader->numberOfSections; $i++) {
            $sectionHeader = new SectionHeader();
            $consume = $sectionHeader->unpack($remaining);
            $sectionHeader->verify();
            $remaining = substr($remaining, $consume);
            $sectionHeaders[] = $sectionHeader;
            // printf("%s 0x%x <> 0x%x\n", $sectionHeader->name, $sectionOffset, $sectionHeader->pointerToRawData);
            if ($sectionOffset !== $sectionHeader->pointerToRawData) {
                // should I throw here?
                throw new \Exception('Section offset mismatch');
            }
            $sectionHeader->sectionData = substr($file, $sectionHeader->pointerToRawData, $sectionHeader->sizeOfRawData);
            $sectionOffset += $sectionHeader->sizeOfRawData;

            $ret += $consume;
        }
        $this->sectionHeaders = $sectionHeaders;

        $remaining = substr($file, $sectionOffset);

        if ($this->attributeCertificate) {
            $remaining = substr($remaining, 0, -strlen($this->attributeCertificate));
        }

        $this->payload = null;
        if ($remaining !== '') {
            $this->payload = $remaining;
        }

        return $ret;
    }

    public function pack(): string
    {
        $this->coffHeader->numberOfSections = count($this->sectionHeaders);

        $dosHeader = $this->dosHeader->pack();
        $dosStub = $this->dosStub->pack();
        $coffHeader = $this->coffHeader->pack();
        $coffOptHeader = $this->coffOptHeader->pack();

        $sectionOffset =
            strlen($dosHeader) + strlen($dosStub) +
            strlen($coffHeader) + strlen($coffOptHeader) +
            (0x28 * $this->coffHeader->numberOfSections);
        if ($sectionOffset % $this->coffOptHeader->fileAlignment !== 0) {
            $sectionOffset += $this->coffOptHeader->fileAlignment - ($sectionOffset % $this->coffOptHeader->fileAlignment);
        }
        $sectionHeadersData = '';
        $sectionsData = '';
        foreach ($this->sectionHeaders as $sectionHeader) {
            $sectionHeader->pointerToRawData = $sectionOffset;
            if (strlen($sectionHeader->sectionData) % $this->coffOptHeader->fileAlignment !== 0) {
                $sectionHeader->sectionData .= 
                    str_repeat("\x00", $this->coffOptHeader->fileAlignment - (strlen($sectionHeader->sectionData) % $this->coffOptHeader->fileAlignment));
            }
            $sectionHeader->sizeOfRawData = strlen($sectionHeader->sectionData);
            $sectionsData .= $sectionHeader->sectionData;
            $sectionOffset += $sectionHeader->sizeOfRawData;
            $sectionHeadersData .= $sectionHeader->pack();
        }

        if ($this->attributeCertificate) {
            $this->coffOptHeader->dataDirectories[4]->virtualAddress = $sectionOffset;
            if ($this->payload) {
                $this->coffOptHeader->dataDirectories[4]->virtualAddress += strlen($this->payload);
            }
            $this->coffOptHeader->dataDirectories[4]->size = strlen($this->attributeCertificate);
        }

        $codeSize = 0;
        $initDataSize = 0;
        $uninitDataSize = 0;
        foreach ($this->sectionHeaders as $sectionHeader) {
            if ($sectionHeader->flags & SectionHeader::IMAGE_SCN_CNT_CODE) {
                $codeSize += $sectionHeader->sizeOfRawData;
            }
            if ($sectionHeader->flags & SectionHeader::IMAGE_SCN_CNT_INITIALIZED_DATA) {
                $initDataSize += $sectionHeader->sizeOfRawData;
            }
            if ($sectionHeader->flags & SectionHeader::IMAGE_SCN_CNT_UNINITIALIZED_DATA) {
                $uninitDataSize += $sectionHeader->sizeOfRawData;
            }
        }
        $lastSection = $this->sectionHeaders[count($this->sectionHeaders) - 1];
        $this->coffOptHeader->sizeOfCode = $codeSize;
        $this->coffOptHeader->sizeOfInitializedData = $initDataSize;
        $this->coffOptHeader->sizeOfUninitializedData = $uninitDataSize;
        $this->coffOptHeader->sizeOfImage = $lastSection->virtualAddress + $lastSection->virtualSize;
        if ($this->coffOptHeader->sizeOfImage % $this->coffOptHeader->sectionAlignment !== 0) {
            $this->coffOptHeader->sizeOfImage += $this->coffOptHeader->sectionAlignment - ($this->coffOptHeader->sizeOfImage % $this->coffOptHeader->sectionAlignment);
        }
        $coffOptHeader = $this->coffOptHeader->pack();

        $ret =
            $dosHeader . $dosStub .
            $coffHeader . $coffOptHeader .
            $sectionHeadersData;
        if (strlen($ret) % $this->coffOptHeader->fileAlignment !== 0) {
            $ret .= str_repeat("\x00", $this->coffOptHeader->fileAlignment - (strlen($ret) % $this->coffOptHeader->fileAlignment));
        }
        $ret .= $sectionsData;

        if ($this->payload) {
            $ret .= $this->payload;
        }

        if ($this->attributeCertificate) {
            $ret .= $this->attributeCertificate;
        }

        return $ret;
    }

    public function verify(): void
    {
        // do nothing
    }

    public function resum(int $offset): void
    {
        // do nothing
    }

    /**
     * fix micro specific resource data
     *
     * @param integer|null $offset if not set, it uses ending of the last section
     * @return void
     */
    public function fixRSRC(?int $offset = null): void
    {
        if ($offset === null) {
            $lastSection = $this->sectionHeaders[count($this->sectionHeaders) - 1];
            $offset = $lastSection->pointerToRawData + $lastSection->sizeOfRawData;
        }
        foreach ($this->sectionHeaders as $sectionHeader) {
            if ($sectionHeader->name !== ".rsrc\0\0\0") {
                continue;
            }
            $rsrc = new RSRC($sectionHeader->virtualAddress);
            $rsrc->unpack($sectionHeader->sectionData);
            foreach ($rsrc->entries as $entry) {
                if ($entry->nameOrId != 12345 /* micro specific RC_DATA id for sfx length */) {
                    continue;
                }
                $sfxLen = $offset;
                $entry->item->entries[0]->item->data = pack('V', $sfxLen);
                break;
            }
            $sectionHeader->sectionData = $rsrc->pack();
            break;
        }
    }

    /**
     * wrap ending paylaod
     *
     * @param boolean $fixRSRC fix micro specific resource data
     * @return void
     */
    public function wrapPayload(bool $fixRSRC = true): void
    {
        $payload = $this->payload;
        $this->payload = null;
        $section = new SectionHeader();
        $section->name = ".payload";
        $section->virtualSize = strlen($payload);
        $lastSection = $this->sectionHeaders[count($this->sectionHeaders) - 1];
        $section->virtualAddress = $lastSection->virtualAddress + $lastSection->virtualSize;
        if ($section->virtualAddress % $this->coffOptHeader->sectionAlignment !== 0) {
            $section->virtualAddress += $this->coffOptHeader->sectionAlignment - ($section->virtualAddress % $this->coffOptHeader->sectionAlignment);
        }
        $section->sizeOfRawData = strlen($payload);
        $payloadPaddingLen = 0;
        if ($section->sizeOfRawData % $this->coffOptHeader->fileAlignment !== 0) {
            $payloadPaddingLen = $this->coffOptHeader->fileAlignment - ($section->sizeOfRawData % $this->coffOptHeader->fileAlignment);
            $section->sizeOfRawData += $payloadPaddingLen;
            $payload = str_repeat("\0", $payloadPaddingLen) . $payload;
        }
        if ($fixRSRC) {
            $this->fixRSRC($lastSection->pointerToRawData + $lastSection->sizeOfRawData + $payloadPaddingLen);
        }
        $section->pointerToRawData = 0; // let packer fix this
        $section->pointerToRelocations = 0;
        $section->pointerToLinenumbers = 0;
        $section->numRelocations = 0;
        $section->numLinenumbers = 0;
        $section->flags =
            SectionHeader::IMAGE_SCN_CNT_INITIALIZED_DATA |
            SectionHeader::IMAGE_SCN_MEM_DISCARDABLE |
            SectionHeader::IMAGE_SCN_MEM_READ;
        // $section->flags =
        //     SectionHeader::IMAGE_SCN_LNK_OTHER |
        //     SectionHeader::IMAGE_SCN_LNK_INFO |
        //     SectionHeader::IMAGE_SCN_LNK_REMOVE;

        $section->sectionData = $payload;
        $this->sectionHeaders[] = $section;
    }
}
