<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Sites;
use DateTimeInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JustinHoltWeb\Walkie\Broadcasting\WalkieBroadcast;
use JustinHoltWeb\Walkie\Changes\Change;
use JustinHoltWeb\Walkie\Channels\ChannelNames;
use Throwable;

use function CraftCms\Cms\currentUser;

/**
 * Turns element changes into broadcasts, and is the PHP API other plugins use to
 * broadcast their own events on an element's channels ({@see send()}).
 */
class Walkie
{
    /** Event names Walkie itself uses; {@see send()} can't reuse them. */
    public const array RESERVED_EVENTS = [Change::CREATED, Change::SAVED, Change::RESTORED, Change::STATUS, Change::DELETED, 'removed'];

    /** Largest custom payload, in bytes of JSON (Pusher's limit is 10 KB per message). */
    public const int MAX_PAYLOAD = 8192;

    public function __construct(private readonly Settings $settings) {}

    /** `entry` or `asset` for an element Walkie broadcasts; null for anything else. */
    public function typeOf(ElementInterface $element): ?string
    {
        return match (true) {
            $element instanceof Entry => $element->getSection() !== null ? 'entry' : null,
            $element instanceof Asset => $element->isFolder ? null : 'asset',
            default => null,
        };
    }

    /** The section (entries) or volume (assets) handle. */
    public function groupOf(ElementInterface $element): ?string
    {
        return match (true) {
            $element instanceof Entry => $element->getSection()?->handle,
            $element instanceof Asset => $element->getVolume()->handle,
            default => null,
        };
    }

    /** Whether the element's section is announced on public channels in its site. */
    public function isPublicGroup(ElementInterface $element): bool
    {
        $section = $element instanceof Entry ? $element->getSection() : null;
        $site = Sites::getSiteById($element->siteId, true);

        return $section !== null && $site !== null && $site->enabled && $this->settings->isPublicSection($section->uid);
    }

    public function change(ElementInterface $element, string $event, ?string $previousStatus): ?Change
    {
        $type = $this->typeOf($element);
        $group = $this->groupOf($element);
        $site = Sites::getSiteById($element->siteId, true);

        if ($type === null || $group === null || $site === null || ! $element->id) {
            return null;
        }

        return new Change(
            event: $event,
            type: $type,
            id: (int) $element->id,
            siteId: $site->id,
            site: $site->handle,
            group: $group,
            status: $event === Change::DELETED ? null : $element->getStatus(),
            previousStatus: $previousStatus,
            publicGroup: $this->isPublicGroup($element),
            causedBy: currentUser()?->getCraftUserId(),
            dateUpdated: $element->dateUpdated instanceof DateTimeInterface ? $element->dateUpdated->format(DATE_ATOM) : null,
        );
    }

    /** Broadcasts a change: to private channels always, to public ones when guests may know. */
    public function dispatch(Change $change): void
    {
        $this->broadcast(new WalkieBroadcast(
            $change->event,
            [
                new PrivateChannel(ChannelNames::element($change->site, $change->type, $change->id)),
                new PrivateChannel(ChannelNames::feed($change->site, $change->type, $change->group)),
            ],
            $change->privatePayload(),
            ...$this->delivery(),
        ));

        if ($change->isPublicNews()) {
            $this->broadcast(new WalkieBroadcast(
                $change->publicEvent(),
                [
                    new Channel(ChannelNames::element($change->site, $change->type, $change->id)),
                    new Channel(ChannelNames::feed($change->site, $change->type, $change->group)),
                ],
                $change->publicPayload(),
                ...$this->delivery(),
            ));
        }
    }

    /**
     * Broadcasts a custom event on an element's private channels (the element's own and
     * its section or volume feed), so only people who may view the element hear it.
     * Pass `public: true` to also send it on the public channels, which only happens
     * while the element is live in a public section.
     *
     * The payload is yours: send ids and small facts, never content a viewer might not
     * be allowed to see. `id`, `type` and `site` are always set by Walkie.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(ElementInterface $element, string $event, array $data = [], bool $public = false): bool
    {
        if (! preg_match('/^[a-z][A-Za-z0-9_\-.]{0,63}$/', $event) || in_array($event, self::RESERVED_EVENTS, true)) {
            throw new InvalidArgumentException("“{$event}” can’t be used as a Walkie event name.");
        }

        $change = $this->change($element, Change::SAVED, $element->getStatus());

        if ($change === null || $element->getIsDraft() || $element->getIsRevision()) {
            return false;
        }

        $payload = ['event' => $event, ...$data, 'id' => $change->id, 'type' => $change->type, 'site' => $change->site];

        if (strlen((string) json_encode($payload)) > self::MAX_PAYLOAD) {
            throw new InvalidArgumentException('Walkie payloads are limited to '.self::MAX_PAYLOAD.' bytes of JSON.');
        }

        $channels = [
            new PrivateChannel(ChannelNames::element($change->site, $change->type, $change->id)),
            new PrivateChannel(ChannelNames::feed($change->site, $change->type, $change->group)),
        ];

        if ($public && $change->publicGroup && $change->isLive()) {
            $channels[] = new Channel(ChannelNames::element($change->site, $change->type, $change->id));
            $channels[] = new Channel(ChannelNames::feed($change->site, $change->type, $change->group));
        }

        return $this->broadcast(new WalkieBroadcast($event, $channels, $payload, ...$this->delivery()));
    }

    /** @return array{now: bool, queueName: ?string, connection: ?string} */
    private function delivery(): array
    {
        return [
            'now' => ! $this->settings->queueBroadcasts,
            'queueName' => $this->settings->queue ?: null,
            'connection' => $this->settings->connection ?: null,
        ];
    }

    private function broadcast(WalkieBroadcast $broadcast): bool
    {
        try {
            event($broadcast);

            return true;
        } catch (Throwable $e) {
            // Never fail a save over a broadcast. Warn once a minute at most.
            if (Cache::add('walkie.broadcastFailed', true, 60)) {
                Log::warning("Walkie couldn’t broadcast “{$broadcast->name}”: {$e->getMessage()}");
            }

            return false;
        }
    }
}
