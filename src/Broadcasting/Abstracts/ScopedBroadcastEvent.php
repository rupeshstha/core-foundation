<?php

namespace CoreFoundation\Broadcasting\Abstracts;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * ScopedBroadcastEvent
 *
 * Specific base for events that should be broadcast on a private scoped channel.
 */
abstract class ScopedBroadcastEvent extends BaseBroadcastEvent
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->broadcastChannelName()),
        ];
    }

    /**
     * The name of the private channel to broadcast on.
     *
     * Example: "tenant.1"
     */
    abstract protected function broadcastChannelName(): string;
}
