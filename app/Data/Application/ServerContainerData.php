<?php

namespace App\Data\Application;

use App\Models\Server;
use Spatie\LaravelData\Data;

class ServerContainerData extends Data
{
    public function __construct(
        public string $startup_command,
        public string $image,
        public int $installed,
        public array $environment,
    ) {}

    public static function fromModel(Server $server, ?array $environment = null): self
    {
        return new self(
            startup_command: $server->startup,
            image: $server->image,
            installed: $server->isInstalled() ? 1 : 0,
            environment: $environment ?? [],
        );
    }
}
