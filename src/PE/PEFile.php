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

    public ?string $sectionPadding = null;

    public ?string $attributeCertificate;
    public ?string $payload;

    public bool $customImageSizes = false;

    public function unpack(string $remaining): int
    {
        $ret = 0;
        $file = $remaining;

        // parse dos MZ header
        $dosHeader = new DOSHeader();
        $consume = $dosHeader->unpack($remaining);
        $ret += $consume;
        $dosHeader->verify();
        $this->dosHeader = $dosHeader;

        $remaining = substr($remaining, $consume);

        // parse dos stub
        $dosStub = new DOSStub(size: $dosHeader->peHeaderOffset - (16 * $dosHeader->headerSize));
        $consume = $dosStub->unpack($remaining);
        $ret += $consume;
        $dosStub->verify();
        $this->dosStub = $dosStub;

        $remaining = substr($remaining, $consume);

        // parse coff header
        $coffHeader = new COFFHeader();
        $consume = $coffHeader->unpack($remaining);
        $ret += $consume;
        $coffHeader->verify();
        $this->coffHeader = $coffHeader;

        $remaining = substr($remaining, $consume);

        // parse coff optional header
        // TODO: dll may have no optional header
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

        // parse section headers
        /** @var SectionHeader[] $sectionHeaders */
        $sectionHeaders = [];
        $sectionOffset = $ret + (0x28 * $coffHeader->numberOfSections);
        if ($sectionOffset % $coffOptHeader->fileAlignment !== 0) {
            // if there is padding between section headers and section data
            // record it, UPX may use this
            $sectionPaddingLen = $coffOptHeader->fileAlignment - ($sectionOffset % $coffOptHeader->fileAlignment);
            $this->sectionPadding = substr(
                $file,
                $sectionOffset,
                $sectionPaddingLen,
            );
            $sectionOffset += $sectionPaddingLen;
        }
        // parser sections
        for ($i = 0; $i < $coffHeader->numberOfSections; $i++) {
            $sectionHeader = new SectionHeader();
            $consume = $sectionHeader->unpack($remaining);
            $sectionHeader->verify();
            $remaining = substr($remaining, $consume);
            $sectionHeaders[] = $sectionHeader;

            if ($sectionHeader->name === "UPX0\0\0\0\0" || $sectionHeader->name === "UPX1\0\0\0\0") {
                // upx needs custom image size
                $this->customImageSizes = true;
            }

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

        // if certificate is present, save it
        if ($this->attributeCertificate) {
            $remaining = substr($remaining, 0, -strlen($this->attributeCertificate));
        }

        // if there is payload, save it
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

        // calculate section offset
        $sectionOffset =
            strlen($dosHeader) + strlen($dosStub) +
            strlen($coffHeader) + strlen($coffOptHeader) +
            (0x28 * $this->coffHeader->numberOfSections);
        if ($sectionOffset % $this->coffOptHeader->fileAlignment !== 0) {
            $sectionPaddingLen = $this->coffOptHeader->fileAlignment -
                ($sectionOffset % $this->coffOptHeader->fileAlignment);
            $sectionOffset += $sectionPaddingLen;
            if ($this->sectionPadding) {
                // for upx, it needs to be reverse-padded
                $this->sectionPadding = substr($this->sectionPadding, -$sectionPaddingLen);
            } else {
                $this->sectionPadding = str_repeat("\x00", $sectionPaddingLen);
            }
        }
        $sectionHeadersData = '';
        $sectionsData = '';
        foreach ($this->sectionHeaders as $sectionHeader) {
            $sectionHeader->pointerToRawData = $sectionOffset;
            if (strlen($sectionHeader->sectionData) % $this->coffOptHeader->fileAlignment !== 0) {
                $sectionHeader->sectionData .=
                    str_repeat("\x00", $this->coffOptHeader->fileAlignment -
                        (strlen($sectionHeader->sectionData) % $this->coffOptHeader->fileAlignment));
            }
            $sectionHeader->sizeOfRawData = strlen($sectionHeader->sectionData);
            $sectionsData .= $sectionHeader->sectionData;
            $sectionOffset += $sectionHeader->sizeOfRawData;
            $sectionHeadersData .= $sectionHeader->pack();
        }

        // calculate sizes
        if ($this->attributeCertificate) {
            $this->coffOptHeader->dataDirectories[4]->virtualAddress = $sectionOffset;
            if ($this->payload) {
                $this->coffOptHeader->dataDirectories[4]->virtualAddress += strlen($this->payload);
            }
            $this->coffOptHeader->dataDirectories[4]->size = strlen($this->attributeCertificate);
        }

        // if custom image sizes are not set, re-calculate them
        // UPX needs custom image sizes, so donot modifiy them when packing
        if (!$this->customImageSizes) {
            // printf(
            //     "org 0x%x 0x%x 0x%x 0x%x\n",
            //     $this->coffOptHeader->sizeOfCode,
            //     $this->coffOptHeader->sizeOfInitializedData,
            //     $this->coffOptHeader->sizeOfUninitializedData,
            //     $this->coffOptHeader->sizeOfImage,
            // );
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
                $this->coffOptHeader->sizeOfImage += $this->coffOptHeader->sectionAlignment -
                    ($this->coffOptHeader->sizeOfImage % $this->coffOptHeader->sectionAlignment);
            }
            // printf(
            //     "mod 0x%x 0x%x 0x%x 0x%x\n",
            //     $this->coffOptHeader->sizeOfCode,
            //     $this->coffOptHeader->sizeOfInitializedData,
            //     $this->coffOptHeader->sizeOfUninitializedData,
            //     $this->coffOptHeader->sizeOfImage,
            // );
        }
        $coffOptHeader = $this->coffOptHeader->pack();

        $ret =
            $dosHeader . $dosStub .
            $coffHeader . $coffOptHeader .
            $sectionHeadersData;
        if (strlen($ret) % $this->coffOptHeader->fileAlignment !== 0) {
            $ret .= $this->sectionPadding;
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
     * make UPX "last_section_rsrc_only"
     *
     * @return void
     */
    public function makeLastOnlyRSRC(): void
    {
        $lastSection = $this->sectionHeaders[count($this->sectionHeaders) - 1];
        $isUPX = false;
        foreach ($this->sectionHeaders as $sectionHeader) {
            if ($sectionHeader->name === "UPX0\0\0\0\0" || $sectionHeader->name === "UPX1\0\0\0\0") {
                $isUPX = true;
                continue;
            }
            if ($sectionHeader->name === "UPX2\0\0\0\0") {
                // already "last_section_rsrc_only"
                $isUPX = false;
                continue;
            }
            if ($sectionHeader->name !== ".rsrc\0\0\0") {
                continue;
            }
            if (!$isUPX) {
                // not upx or already "last_section_rsrc_only"
                break;
            }
            // make it "last_section_rsrc_only"

            // make this section UPX2
            $sectionHeader->name = "UPX2\0\0\0\0";

            // parse rsrc
            $rsrc = new RSRC($sectionHeader->virtualAddress);
            $rsrc->unpack($sectionHeader->sectionData);

            // calculate new rsrc rva
            $newRVA = $lastSection->virtualAddress + $lastSection->virtualSize;
            if ($newRVA % $this->coffOptHeader->sectionAlignment !== 0) {
                $newRVA += $this->coffOptHeader->sectionAlignment -
                    ($newRVA % $this->coffOptHeader->sectionAlignment);
            }
            // generate new RSRC
            $rsrc->baseRVA = $newRVA;
            $rsrcData = $rsrc->pack();
            $rsrcLen = strlen($rsrcData);
            if ($rsrcLen % $this->coffOptHeader->fileAlignment !== 0) {
                $rsrcPaddingLen = $this->coffOptHeader->fileAlignment - ($rsrcLen % $this->coffOptHeader->fileAlignment);
                $rsrcData .= str_repeat("\0", $rsrcPaddingLen);
            }

            // create new rsrc
            $rsrc->baseRVA = $newRVA;
            $newSection = new SectionHeader();
            $newSection->name = ".rsrc\0\0\0";
            $newSection->virtualSize = $rsrcLen;
            $newSection->virtualAddress = $newRVA;
            $newSection->sizeOfRawData = strlen($rsrcData);
            $newSection->pointerToRawData = $lastSection->pointerToRawData + $lastSection->sizeOfRawData;
            $newSection->pointerToRelocations = 0;
            $newSection->pointerToLinenumbers = 0;
            $newSection->numRelocations = 0;
            $newSection->numLinenumbers = 0;
            $newSection->flags =
                SectionHeader::IMAGE_SCN_CNT_INITIALIZED_DATA |
                SectionHeader::IMAGE_SCN_MEM_READ |
                SectionHeader::IMAGE_SCN_MEM_WRITE;
            $newSection->sectionData = $rsrcData;
            break;
        }

        if ($isUPX) {
            $this->sectionHeaders[] = $newSection;
            $this->coffOptHeader->sizeOfImage += $newSection->virtualSize;
            if ($this->coffOptHeader->sizeOfImage % $this->coffOptHeader->sectionAlignment !== 0) {
                $this->coffOptHeader->sizeOfImage += $this->coffOptHeader->sectionAlignment -
                    ($this->coffOptHeader->sizeOfImage % $this->coffOptHeader->sectionAlignment);
            }
            $this->coffOptHeader->sizeOfInitializedData += $newSection->virtualSize;
            $this->coffOptHeader->dataDirectories[2]->size = $newSection->virtualSize;
            $this->coffOptHeader->dataDirectories[2]->virtualAddress = $newSection->virtualAddress;
        }
    }

    /**
     * fix micro specific resource data
     *
     * @param integer|null $offset if not set, it uses ending of the last section
     * @return void
     */
    public function fixRSRC(?int $offset = null): void
    {
        $this->makeLastOnlyRSRC();
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
            foreach ($rsrc->dirEntries as $entry) {
                if ($entry->nameOrId != 12345 /* micro specific RC_DATA id for sfx length */) {
                    continue;
                }
                $sfxLen = $offset;
                $entry->item->entries[0]->item->data = pack('V', $sfxLen);
                $entry->item->entries[0]->item->size = 4;
                break;
            }
            $rsrcData = $rsrc->pack();
            $rsrcLen = strlen($rsrcData);
            if ($rsrcLen % $this->coffOptHeader->fileAlignment !== 0) {
                $rsrcPaddingLen = $this->coffOptHeader->fileAlignment - ($rsrcLen % $this->coffOptHeader->fileAlignment);
                $rsrcData .= str_repeat("\0", $rsrcPaddingLen);
            }
            // just save it back
            $sectionHeader->sectionData = $rsrcData;
            $sectionHeader->virtualSize = $rsrcLen;
            $sectionHeader->sizeOfRawData = strlen($rsrcData);

            $this->coffOptHeader->dataDirectories[2]->size = $sectionHeader->virtualSize;
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
        // for upx
        $this->makeLastOnlyRSRC();
        $lastSection = $this->sectionHeaders[count($this->sectionHeaders) - 1];
        // make payload left-padding
        $payload = $this->payload;
        $this->payload = null;
        $payloadLen = strlen($payload);
        $payloadPaddingLen = 0;
        if ($payloadLen % $this->coffOptHeader->fileAlignment !== 0) {
            $payloadPaddingLen = $this->coffOptHeader->fileAlignment -
                ($payloadLen % $this->coffOptHeader->fileAlignment);
            $payload = str_repeat("\0", $payloadPaddingLen) . $payload;
        }
        if ($fixRSRC) {
            // then change it to payload start
            $this->fixRSRC($lastSection->pointerToRawData + $lastSection->sizeOfRawData + $payloadPaddingLen);
        }

        $section = new SectionHeader();
        $section->name = ".payload";
        $section->virtualSize = $payloadLen;
        $section->virtualAddress = $lastSection->virtualAddress + $lastSection->virtualSize;
        if ($section->virtualAddress % $this->coffOptHeader->sectionAlignment !== 0) {
            $section->virtualAddress += $this->coffOptHeader->sectionAlignment -
                ($section->virtualAddress % $this->coffOptHeader->sectionAlignment);
        }
        $section->sizeOfRawData = $payloadLen + $payloadPaddingLen;
        $section->pointerToRawData = $lastSection->pointerToRawData + $lastSection->sizeOfRawData;
        $section->pointerToRelocations = 0;
        $section->pointerToLinenumbers = 0;
        $section->numRelocations = 0;
        $section->numLinenumbers = 0;
        $section->flags =
            SectionHeader::IMAGE_SCN_CNT_INITIALIZED_DATA |
            SectionHeader::IMAGE_SCN_MEM_DISCARDABLE |
            SectionHeader::IMAGE_SCN_MEM_READ;

        $section->sectionData = $payload;
        $this->sectionHeaders[] = $section;
    }
}
