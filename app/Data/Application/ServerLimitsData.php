<?php

namespace App\Data\Application;

use App\Models\Server;
use Spatie\LaravelData\Data;

class ServerLimitsData extends Data
{
    public function __construct(
        public int $memory,
        public int $swap,
        public int $disk,
        public int $io,
        public int $cpu,
        public ?string $threads,
        public bool $oom_disabled,
        public bool $oom_killer,
    ) {}

    public static function fromModel(Server $server): self
    {
        return new self(
            memory: $server->memory,
            swap: $server->swap,
            disk: $server->disk,
            io: $server->io,
            cpu: $server->cpu,
            threads: $server->threads,
            oom_disabled: !$server->oom_killer,
            oom_killer: $server->oom_killer,
        );
    }
}
