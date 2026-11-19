<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie;

use Closure;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Facades\Sections;

use function CraftCms\Cms\t;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Broadcast when an entry or asset is created or saved. */
    public bool $broadcastSaves = true;

    /** Broadcast when an entry or asset is deleted (or restored). */
    public bool $broadcastDeletes = true;

    /** Broadcast scheduled status changes (a post date or expiry date passing). */
    public bool $broadcastStatusChanges = true;

    /** Allow presence channels: who else is looking at an element. */
    public bool $presence = false;

    /**
     * Sections whose live entries are announced on public (guest) channels, by UID.
     *
     * @var list<string>|string|null
     */
    public array|string|null $publicSections = [];

    /** The broadcast connection (config/broadcasting.php). Blank: the app's default. */
    public ?string $connection = null;

    /** Send broadcasts from the queue instead of during the request. */
    public bool $queueBroadcasts = true;

    /** The queue name for broadcasts. Blank: the connection's default queue. */
    public ?string $queue = null;

    /** Register `/broadcasting/auth` when the app doesn't already have it. */
    public bool $registerAuthRoute = true;

    /** The WebSocket host browsers connect to, when it differs from the server-side one. */
    public ?string $clientHost = null;

    /** The WebSocket port browsers connect to, when it differs from the server-side one. */
    public ?int $clientPort = null;

    /** @return list<string> */
    public function publicSectionUids(): array
    {
        $uids = is_string($this->publicSections)
            ? preg_split('/[\s,]+/', $this->publicSections, -1, PREG_SPLIT_NO_EMPTY)
            : ($this->publicSections ?? []);

        return array_values(array_filter(array_map('strval', $uids ?: []), fn (string $uid) => $uid !== '*'));
    }

    public function isPublicSection(string $uid): bool
    {
        return in_array($uid, $this->publicSectionUids(), true);
    }

    /** @return array<string, mixed> */
    public function getRules(): array
    {
        return [
            'connection' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $value !== '' && ! is_array(config("broadcasting.connections.$value"))) {
                    $fail(t('There’s no “{name}” broadcast connection in config/broadcasting.php.', ['name' => $value], category: 'walkie'));
                }
            }],
            'queue' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-.:]+$/'],
            'clientHost' => ['nullable', 'string', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'clientPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'publicSections' => [function (string $attribute, mixed $value, Closure $fail): void {
                foreach ($this->publicSectionUids() as $uid) {
                    if (Sections::getSectionByUid($uid) === null) {
                        $fail(t('Unknown section.', category: 'walkie'));

                        return;
                    }
                }
            }],
        ];
    }
}
