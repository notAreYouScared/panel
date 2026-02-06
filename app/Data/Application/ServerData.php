<?php

namespace App\Data\Application;

use App\Data\PanelData;
use App\Models\Server;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Optional;

class ServerData extends PanelData
{
    public function __construct(
        public int $id,
        public ?string $external_id,
        public string $uuid,
        public string $identifier,
        public string $name,
        public string $description,
        public ?string $status,
        public bool $suspended,
        public ServerLimitsData $limits,
        public ServerFeatureLimitsData $feature_limits,
        public int $user,
        public int $node,
        public ?int $allocation,
        public int $egg,
        public ServerContainerData $container,
        public string $created_at,
        public string $updated_at,
        /** @var array<string, mixed> */
        #[Computed]
        public array $relationships = [],
    ) {}

    /**
     * Create from a Server model.
     */
    public static function fromModel(Server $server, ?array $environment = null): self
    {
        return new self(
            id: $server->getKey(),
            external_id: $server->external_id,
            uuid: $server->uuid,
            identifier: $server->uuid_short,
            name: $server->name,
            description: $server->description,
            status: $server->status?->value,
            suspended: $server->isSuspended(),
            limits: ServerLimitsData::fromModel($server),
            feature_limits: ServerFeatureLimitsData::fromModel($server),
            user: $server->owner_id,
            node: $server->node_id,
            allocation: $server->allocation_id,
            egg: $server->egg_id,
            container: ServerContainerData::fromModel($server, $environment),
            created_at: self::formatTimestamp($server->created_at),
            updated_at: self::formatTimestamp($server->updated_at),
        );
    }

    public function getResourceName(): string
    {
        return Server::RESOURCE_NAME;
    }

    /**
     * Format timestamp to ISO-8601 format for API responses.
     */
    private static function formatTimestamp(\Carbon\CarbonInterface $timestamp): string
    {
        return $timestamp
            ->setTimezone('UTC')
            ->toAtomString();
    }
}
