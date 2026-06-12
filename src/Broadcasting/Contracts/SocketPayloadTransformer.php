<?php

namespace CoreFoundation\Broadcasting\Contracts;

/**
 * SocketPayloadTransformer
 *
 * Interface for classes that transform domain data into a structured
 * payload for WebSocket broadcasting.
 *
 * @template T
 */
interface SocketPayloadTransformer
{
    /**
     * Transform the source data into a broadcastable array.
     *
     * @param T $data
     * @return array<string, mixed>
     */
    public function transform(mixed $data): array;
}
