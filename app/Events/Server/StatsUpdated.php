<?php

namespace App\Events\Server;

use App\Events\Event;
use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StatsUpdated extends Event implements ShouldBroadcast
{
    use InteractsWithBroadcasting, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public Server $server,
        public array $stats,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array<int, Channel>
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel("server.{$this->server->id}.stats");
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stats-updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stats' => $this->stats,
        ];
    }
}
