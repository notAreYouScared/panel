<?php

namespace App\Data\Application;

use App\Models\Server;
use Spatie\LaravelData\Data;

class ServerFeatureLimitsData extends Data
{
    public function __construct(
        public ?int $databases,
        public ?int $allocations,
        public ?int $backups,
    ) {}

    public static function fromModel(Server $server): self
    {
        return new self(
            databases: $server->database_limit,
            allocations: $server->allocation_limit,
            backups: $server->backup_limit,
        );
    }
}
