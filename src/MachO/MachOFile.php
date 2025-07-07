<?php

declare(strict_types=1);

namespace MachO;

use Unpacker\PackItem;
use Unpacker\CommonPack;
use Unpacker\NullVerifier;

class MachOFile implements CommonPack
{
    use NullVerifier;

    public MachOHeader $header;

    public string $segments;

    public ?string $payload = null;

    public function unpack(string $remaining): int
    {
        $header = new MachOHeader();
        $header->unpack($remaining);
        $header->verify();
        // $remaining = substr($data, $consume);
        $this->header = $header;

        $machoLength = 0;
        foreach ($header->loadCommands as $cmd) {
            if ($cmd instanceof SegmentCommand64 || $cmd instanceof SegmentCommand32) {
                // printf("%0.16s: %08x %08x %08x %08x\n", $cmd->name, $cmd->fileOffset, $cmd->fileSize, $cmd->vmAddr, $cmd->vmSize);
                $machoLength = max($machoLength, $cmd->fileOffset + $cmd->fileSize);
            }
            // printf("%08x\n", $cmd->cmd);
        }

        $this->segments = substr($remaining, 0, $machoLength);
        $this->payload = null;
        if ($machoLength !== strlen($remaining)) {
            $this->payload = substr($remaining, $machoLength);
        }

        // always consume the whole remaining data
        return strlen($remaining);
    }

    public function pack(): string
    {
        $headerData = $this->header->pack();

        return $headerData . substr($this->segments, strlen($headerData)) . ($this->payload ?? '');
    }

    const LINKEDIT_FILE_ALIGN = 0x10;
    const LINKEDIT_VM_ALIGN = 0x4000;

    public function wrapPayload(): void
    {
        if ($this->payload === null) {
            throw new \Exception('no payload to wrap');
        }

        $cmd = null;
        foreach ($this->header->loadCommands as $cmd) {
            if ($cmd instanceof SegmentCommand64 || $cmd instanceof SegmentCommand32) {
                if ($cmd->name === "__LINKEDIT\0\0\0\0\0\0") {
                    break;
                }
            }
        }

        if ($cmd === null) {
            throw new \Exception('no __LINKEDIT segment found');
        }
        /** @var SegmentCommand64|SegmentCommand32 $cmd */

        $fileEnd = $cmd->fileOffset + $cmd->fileSize;

        $cmd->fileSize += strlen($this->payload);
        if ($cmd->fileSize % static::LINKEDIT_FILE_ALIGN !== 0) {
            $payloadPadding = static::LINKEDIT_FILE_ALIGN - ($cmd->fileSize % static::LINKEDIT_FILE_ALIGN);
            $cmd->fileSize += $payloadPadding;
        }
        $appendSize = strlen($this->payload) + $payloadPadding;

        $cmd->vmSize = $cmd->fileSize;
        if ($cmd->vmSize % static::LINKEDIT_VM_ALIGN !== 0) {
            $cmd->vmSize += static::LINKEDIT_VM_ALIGN - ($cmd->vmSize % static::LINKEDIT_VM_ALIGN);
        }
        $this->segments .= $this->payload . str_repeat("\0", $payloadPadding);
        $this->payload = null;

        $cmd = null;
        foreach ($this->header->loadCommands as $cmd) {
            if ($cmd instanceof SymbolTableCommand) {
                break;
            }
        }

        if ($cmd === null) {
            throw new \Exception('no symtab segment found');
        }
        /** @var SymbolTableCommand $cmd */

        if ($cmd->stringOff + $cmd->stringSize != $fileEnd) {
            // TODO: handle this case
            throw new \Exception('symtab string table not at end of __LINKEDIT');
        }

        $cmd->stringSize += $appendSize;
    }

    static function generateFakeX86Code(string $message, int $exitCode, int $loadAddr = 0x1000): array
    {
        $size = 0x1000;
        $textAddr = $loadAddr + $size - strlen($message);
        $code = '';

        $code .= pack('CC', 0x6a, strlen($message)); // push message length
        $code .= pack('CV', 0x68, $textAddr); // push message
        $code .= pack('CC', 0x6a, 2); // push stderr
        $code .= pack('CV', 0xb8, 4); // mov eax, 4 (NR_write)
        $code .= pack('CCC', 0x83, 0xec, 4); // sub esp, 4 align stack
        $code .= pack('CC', 0xcd, 0x80); // int 0x80 syscall

        $code .= pack('CC', 0x6a, $exitCode); // push exit code
        $code .= pack('CV', 0xb8, 1); // mov eax, 1 (NR_exit)
        $code .= pack('CCC', 0x83, 0xec, 12); // sub esp, 12 align stack
        $code .= pack('CC', 0xcd, 0x80); // int 0x80 syscall
        $code .= pack('C', 0xcc); // int 0x03 breakpoint

        $padding1Length = intdiv($size - strlen($code) - strlen($message), 4) * 4;
        $padding2Length = $size - $padding1Length - strlen($code) - strlen($message);

        return [
            $padding1Length + $loadAddr,
            str_repeat("\0", $padding1Length) . $code . str_repeat("\xcc", $padding2Length) . $message
        ];
    }

    /**
     * create a fake mach-o file with a payload, the fake mach-o file will write a message to stderr and exit
     * this is for fat mach-o wrapping
     * @param int $start the start of the generated fake mach-o file
     * @param string $payload the payload to be appended to the mach-o file
     * @param int $payloadAlign the alignment of the payload, default is 0x1000, this is for the generated mach-o file to be executable. If only signable is needed, set it to 0x10, can be any power of 2.
     */
    static public function createFakeMachO(int $start, string $payload, int $payloadAlign = 0x1000): static
    {
        $macho = new MachOFile();
        $macho->header = new MachOHeader();
        $macho->header->magic = MachOHeader::MH_MAGIC;
        $macho->header->cpuType = MachOHeader::CPU_TYPE_X86;
        $macho->header->cpuSubtype = 0;
        $macho->header->fileType = MachOHeader::MH_EXECUTE;
        $macho->header->nCmds = 0;
        $macho->header->sizeOfCmds = 0;
        $macho->header->flags = 1 /* MH_NOUNDEFS */;
        $macho->header->loadCommands = [];

        [$entryAddr, $code] = static::generateFakeX86Code("This program cannot be run in i386 mode.\n", 1);
        $macho->segments = $code;

        $dataLen = (strlen($payload) + 16 + $payloadAlign - 1) & ~($payloadAlign - 1);

        /**
         * file Offset         | VMA                     | size                    | usage
         * 0x0                 | 0x1000                  | ?                       | macho header
         * machoHeaderLen      | 0x1000 + machoHeaderLen | ?                       | padding to text section, zeros
         * $entryAddr - 0x1000 | $entryAddr              | ?                       | text section, code
         * 0x1000              | 0x2000                  | 16                      | sfxsize section
         * 0x1010              | 0x2010                  | payloadLen padto 0x1000 | payload
         * -                   | 0                       | 0                       | __LINKEDIT
         */

        // Segment __TEXT
        {
            $segment = SegmentCommand32::createEmpty();
            $macho->header->loadCommands[] = $segment;
            $macho->header->nCmds++;
            $segment->name = "__TEXT\0\0\0\0\0\0\0\0\0\0";
            $segment->vmAddr = 0x1000;
            $segment->vmSize = 0x1000;
            $segment->fileOffset = 0;
            $segment->fileSize = 0x1000;
            $segment->initProtect = 0x5;
            $segment->maxProtect = 0x5;

            // Section __text
            {
                $section = SegmentSection32::createEmpty();
                $segment->sections[] = $section;
                $segment->nSections++;
                $segment->cmdSize += 0x44 /* sizeof(SegmentSection32) */;
                $section->name = "__text\0\0\0\0\0\0\0\0\0\0";
                $section->segmentName = "__TEXT\0\0\0\0\0\0\0\0\0\0";
                $section->addr = $entryAddr;
                $section->size = 0x2000 - $entryAddr;
                $section->offset = $entryAddr - 0x1000;
                $section->align = 2; // 4 bytes align
                $section->flags = 0x80000400;
            }

            $macho->header->sizeOfCmds += $segment->cmdSize;
        }
        // Segment __DATA
        {
            $segment = SegmentCommand32::createEmpty();
            $macho->header->loadCommands[] = $segment;
            $macho->header->nCmds++;
            $segment->name = "__DATA\0\0\0\0\0\0\0\0\0\0";
            $segment->vmAddr = 0x2000;
            $segment->vmSize = 0x1000;
            $segment->fileOffset = 0x1000;
            $segment->fileSize = $dataLen;
            $segment->initProtect = 0x3;
            $segment->maxProtect = 0x3;

            // Section __micro_sfxsize
            {
                $section = SegmentSection32::createEmpty();
                $segment->sections[] = $section;
                $segment->nSections++;
                $segment->cmdSize += 0x44 /* sizeof(SegmentSection32) */;
                $section->name = "__micro_sfxsize\0";
                $section->segmentName = "__DATA\0\0\0\0\0\0\0\0\0\0";
                $section->addr = 0x2000;
                $section->size = 16;
                $section->offset = 0x1000;
                $section->align = 4; // 16 bytes align
            }

            // Section __micro_payload
            {
                $section = SegmentSection32::createEmpty();
                $segment->sections[] = $section;
                $segment->nSections++;
                $segment->cmdSize += 0x44 /* sizeof(SegmentSection32) */;
                $section->name = "__micro_payload\0";
                $section->segmentName = "__DATA\0\0\0\0\0\0\0\0\0\0";
                $section->addr = 0x2010;
                $section->size = strlen($payload);
                $section->offset = 0x1010;
                $section->align = 4; // 16 bytes align
            }

            $macho->header->sizeOfCmds += $segment->cmdSize;
        }
        // Segment __LINKEDIT
        {
            $segment = SegmentCommand32::createEmpty();
            $macho->header->loadCommands[] = $segment;
            $macho->header->nCmds++;
            $segment->name = "__LINKEDIT\0\0\0\0\0\0";
            // must align to 0x1000 for executable
            $segment->vmAddr = (0x1000 + 0x1000 + $dataLen + $payloadAlign - 1) & ~($payloadAlign - 1);
            $segment->vmSize = 0; // no data
            // fileOffset + fileSize must align to 0x1000 (for executable) + be the file end (for signing)
            $segment->fileOffset = 0x1000 + $dataLen;
            $segment->fileSize = 0; // no data
            $segment->initProtect = 0x1;
            $segment->maxProtect = 0x1;

            $macho->header->sizeOfCmds += $segment->cmdSize;
        }
        // LC_UNIXTHREAD
        {
            $cmd = UnixThreadCommandX86::createEmpty();
            $macho->header->loadCommands[] = $cmd;
            $macho->header->nCmds++;
            $macho->header->sizeOfCmds += $cmd->cmdSize;
            $cmd->registers[10 /* eip */] = $entryAddr;
        }

        $macho->payload =
            pack(
                'JJ',
                $start + 0x1000 + 16,
                $start + 0x1000 + 16 + strlen($payload),
            ) .
            $payload .
            str_repeat("\0", $payloadAlign - ((strlen($payload) + 16) % $payloadAlign));

        return $macho;
    }
}
