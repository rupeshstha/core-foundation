<?php

namespace CoreFoundation\Broadcasting\Abstracts;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * TenantBroadcastEvent
 *
 * Specific base for events that should be broadcast on a tenant-private channel.
 */
abstract class TenantBroadcastEvent extends BaseBroadcastEvent
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->getTenantId()}"),
        ];
    }
}
