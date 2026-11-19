<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Changes;

/**
 * One element's change in one site, as Walkie broadcasts it.
 *
 * Only ids, handles and statuses: never field content, titles, URLs or authors.
 * Clients refetch what they need through their own (permission-checked) requests.
 */
final class Change
{
    public const string CREATED = 'created';

    public const string SAVED = 'saved';

    public const string RESTORED = 'restored';

    public const string STATUS = 'status';

    public const string DELETED = 'deleted';

    /** Which event wins when one element changes more than once before the broadcast goes out. */
    private const array PRECEDENCE = [self::STATUS => 0, self::SAVED => 1, self::RESTORED => 2, self::CREATED => 3, self::DELETED => 4];

    /**
     * @param  'entry'|'asset'  $type
     * @param  bool  $publicGroup  whether the element's section is announced on public channels in this (enabled) site
     */
    public function __construct(
        public readonly string $event,
        public readonly string $type,
        public readonly int $id,
        public readonly int $siteId,
        public readonly string $site,
        public readonly string $group,
        public readonly ?string $status,
        public readonly ?string $previousStatus,
        public readonly bool $publicGroup = false,
        public readonly ?int $causedBy = null,
        public readonly ?string $dateUpdated = null,
    ) {}

    public function key(): string
    {
        return "$this->type:$this->id:$this->siteId";
    }

    /** Folds a later change to the same element and site into this one. */
    public function merge(self $later): self
    {
        $event = self::PRECEDENCE[$later->event] >= self::PRECEDENCE[$this->event] ? $later->event : $this->event;

        // Deleted then restored before the broadcast went out: a restore.
        if ($this->event === self::DELETED && $later->event === self::RESTORED) {
            $event = self::RESTORED;
        }

        return new self(
            $event, $later->type, $later->id, $later->siteId, $later->site, $later->group,
            $later->status, $this->previousStatus, $later->publicGroup, $later->causedBy ?? $this->causedBy,
            $later->dateUpdated ?? $this->dateUpdated,
        );
    }

    public function withEvent(string $event): self
    {
        return new self($event, $this->type, $this->id, $this->siteId, $this->site, $this->group, $this->status,
            $this->previousStatus, $this->publicGroup, $this->causedBy, $this->dateUpdated);
    }

    public function isLive(): bool
    {
        return $this->event !== self::DELETED && $this->status === 'live';
    }

    public function wasLive(): bool
    {
        return $this->previousStatus === 'live';
    }

    /** Whether guests are told about this change: it is, or just stopped being, public. */
    public function isPublicNews(): bool
    {
        return $this->publicGroup && $this->type === 'entry' && ($this->isLive() || $this->wasLive());
    }

    public function statusChanged(): bool
    {
        return $this->status !== $this->previousStatus;
    }

    /**
     * What viewers with permission receive on private channels.
     *
     * @return array<string, int|string>
     */
    public function privatePayload(): array
    {
        return array_filter([
            'event' => $this->event,
            'id' => $this->id,
            'type' => $this->type,
            'site' => $this->site,
            ($this->type === 'entry' ? 'section' : 'volume') => $this->group,
            'status' => $this->status,
            'previousStatus' => $this->previousStatus,
            'causedBy' => $this->causedBy,
            'dateUpdated' => $this->dateUpdated,
        ], fn ($v) => $v !== null);
    }

    /** The public event name: the entry is live (`saved`) or no longer is (`removed`). */
    public function publicEvent(): string
    {
        return $this->isLive() ? 'saved' : 'removed';
    }

    /**
     * What guests receive on public channels.
     *
     * @return array<string, int|string>
     */
    public function publicPayload(): array
    {
        return [
            'event' => $this->publicEvent(),
            'id' => $this->id,
            'type' => $this->type,
            'site' => $this->site,
            'section' => $this->group,
        ];
    }
}
