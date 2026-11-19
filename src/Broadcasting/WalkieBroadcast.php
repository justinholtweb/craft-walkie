<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;

/**
 * A message Walkie broadcasts. Also a plain Laravel event, so PHP code can listen for it.
 *
 * Rescued: a broadcaster that's down or misconfigured is reported, never fails the save.
 */
class WalkieBroadcast implements ShouldBroadcast, ShouldRescue
{
    use InteractsWithBroadcasting;

    /**
     * @param  list<Channel>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $name,
        public readonly array $channels,
        public readonly array $payload,
        public readonly bool $now = false,
        public readonly ?string $queueName = null,
        ?string $connection = null,
    ) {
        $this->broadcastVia($connection);
    }

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return $this->channels;
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function shouldBroadcastNow(): bool
    {
        return $this->now;
    }

    public function broadcastQueue(): ?string
    {
        return $this->queueName;
    }
}
