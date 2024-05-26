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

    public array $segments;

    public ?string $payload = null;

    public function unpack(string $data): int
    {
        $header = new MachOHeader();
        $consume = $header->unpack($data);
        $header->verify();
        // $remaining = substr($data, $consume);
        $this->header = $header;

        $machoLength = 0;
        $segments = [];
        foreach ($header->loadCommands as $cmd) {
            if ($cmd instanceof SegmentCommand64 || $cmd instanceof SegmentCommand32) {
                // printf("%0.16s: %08x %08x %08x %08x\n", $cmd->name, $cmd->fileOffset, $cmd->fileSize, $cmd->vmAddr, $cmd->vmSize);
                $segments[] = substr($data, $cmd->fileOffset, $cmd->fileSize);
                $machoLength = max($machoLength, $cmd->fileOffset + $cmd->fileSize);
            }
            // printf("%08x\n", $cmd->cmd);
        }

        $this->segments = $segments;
        $this->payload = null;
        if ($machoLength !== strlen($data)) {
            $this->payload = substr($data, $machoLength);
        }

        return strlen($data);
    }

    public function pack(): string
    {
        $headerData = $this->header->pack();

        $segmentData = implode('', $this->segments);

        return $headerData . substr($segmentData, strlen($headerData)) . ($this->payload ?? '');
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

        $fileEnd = $cmd->fileOffset + $cmd->fileSize;

        if ($cmd === null) {
            throw new \Exception('no __LINKEDIT segment found');
        }

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
        $this->segments[count($this->segments) - 1] .= $this->payload . str_repeat("\0", $payloadPadding);
        $this->payload = null;

        foreach ($this->header->loadCommands as $cmd) {
            if ($cmd instanceof SymbolTableCommand) {
                break;
            }
        }

        if ($cmd === null) {
            throw new \Exception('no symtab segment found');
        }
        if ($cmd->stringOff + $cmd->stringSize != $fileEnd) {
            // TODO: handle this case
            throw new \Exception('symtab string table not at end of __LINKEDIT');
        }

        $cmd->stringSize += $appendSize;
    }
}
