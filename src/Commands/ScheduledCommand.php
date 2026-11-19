<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Commands;

use Carbon\CarbonInterface;
use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Entry\Elements\Entry;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JustinHoltWeb\Walkie\Changes\Change;
use JustinHoltWeb\Walkie\Changes\Outbox;
use JustinHoltWeb\Walkie\Settings;
use JustinHoltWeb\Walkie\Walkie;

/**
 * Craft fires no event when a post date or expiry date passes, so an entry can go live
 * (or expire) without anyone hearing. Run every minute by the scheduler; broadcasts a
 * `status` message for each entry whose status changed since the last run.
 */
class ScheduledCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:walkie:scheduled {--since= : Override the window start (any strtotime string, at most a day ago)}';

    protected $description = 'Broadcasts entries that went live or expired since the last run';

    public const string CACHE_KEY = 'walkie.scheduled.lastRun';

    public function handle(Walkie $walkie, Outbox $outbox, Settings $settings): int
    {
        if (! $settings->broadcastStatusChanges) {
            $this->components->info('Status-change broadcasts are switched off.');

            return self::SUCCESS;
        }

        $now = now();
        $since = $this->option('since') ? now()->parse((string) $this->option('since')) : Cache::get(self::CACHE_KEY, $now->copy()->subMinutes(5));
        $since = $since instanceof CarbonInterface ? $since->max($now->copy()->subDay()) : $now->copy()->subMinutes(5);
        $window = ['and', '> '.$since->toIso8601String(), '<= '.$now->toIso8601String()];
        $sent = 0;

        $entries = [
            ...Entry::find()->site('*')->status(null)->postDate($window)->all(),
            ...Entry::find()->site('*')->status(null)->expiryDate($window)->all(),
        ];

        $seen = [];
        foreach ($entries as $entry) {
            $key = "$entry->id:$entry->siteId";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $current = $entry->getStatus();
            $previous = $this->statusAt($entry, $since);

            if ($previous === null || $current === $previous || ! in_array($current, [Entry::STATUS_LIVE, Entry::STATUS_EXPIRED], true)) {
                continue;
            }

            if ($change = $walkie->change($entry, Change::STATUS, $previous)) {
                $outbox->push($change);
                $sent++;
            }
        }

        Cache::forever(self::CACHE_KEY, $now);
        $this->components->info("Broadcast {$sent} scheduled status changes.");

        return self::SUCCESS;
    }

    /** An enabled entry's status at a moment, from its dates. */
    private function statusAt(Entry $entry, CarbonInterface $at): ?string
    {
        $post = $entry->postDate;
        $expiry = $entry->expiryDate;

        if (! $post instanceof DateTimeInterface) {
            return null;
        }

        return match (true) {
            $post > $at => Entry::STATUS_PENDING,
            $expiry instanceof DateTimeInterface && $expiry <= $at => Entry::STATUS_EXPIRED,
            default => Entry::STATUS_LIVE,
        };
    }
}
