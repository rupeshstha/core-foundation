<?php

namespace CoreFoundation\Broadcasting\Abstracts;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * BaseBroadcastEvent
 *
 * A foundational class for all broadcastable events in the platform.
 * Ensures consistent structure and isolation for real-time events.
 */
abstract class BaseBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The name of the queue on which the event should be placed.
     */
    public string $broadcastQueue = 'broadcasts';

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->broadcastAs(),
            'payload' => $this->getPayload(),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'scope_id' => $this->getScopeId(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return str_replace('\\', '.', static::class);
    }

    /**
     * Get the actual payload for the event.
     *
     * @return array<string, mixed>
     */
    abstract protected function getPayload(): array;

    /**
     * Get the scope ID for isolation (e.g. tenant ID).
     */
    abstract protected function getScopeId(): int|string|null;
}
