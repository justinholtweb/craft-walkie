<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Sections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Walkie\Client\EchoConfig;
use JustinHoltWeb\Walkie\Settings;
use Throwable;

/** What Walkie is broadcasting through, and to whom. Prints no secrets. */
class StatusCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:walkie:status';

    protected $description = 'Shows Walkie’s broadcast connection, auth route and public sections';

    public function handle(Settings $settings): int
    {
        $name = $settings->connection ?: config('broadcasting.default');
        $connection = is_string($name) ? config("broadcasting.connections.$name") : null;
        $driver = is_array($connection) ? (string) ($connection['driver'] ?? '?') : 'missing';
        try {
            $route = Route::getRoutes()->match(request()->create('/broadcasting/auth', 'POST'));
            $owner = $route->getName() === 'walkie.auth' ? 'Walkie' : ($route->isFallback ? 'not registered' : 'the app’s own');
        } catch (Throwable) {
            $owner = 'not registered';
        }
        $echo = EchoConfig::fromConnection(is_array($connection) ? $connection : null, $settings->clientHost, $settings->clientPort);
        $public = array_map(fn (string $uid) => Sections::getSectionByUid($uid)->handle ?? "unknown ($uid)", $settings->publicSectionUids());

        $this->components->twoColumnDetail('Connection', ($name ?: 'none')." ($driver)");
        $this->components->twoColumnDetail('Delivery', $settings->queueBroadcasts ? 'queued'.($settings->queue ? " ($settings->queue)" : '') : 'immediate');
        $this->components->twoColumnDetail('Browser connection', $echo ? "{$echo['broadcaster']} ".($echo['wsHost'] ?? $echo['cluster'] ?? '') : 'not available for this driver');
        $this->components->twoColumnDetail('/broadcasting/auth', $owner);
        $this->components->twoColumnDetail('Saves / deletes / status', implode(' / ', array_map(fn (bool $b) => $b ? 'on' : 'off', [$settings->broadcastSaves, $settings->broadcastDeletes, $settings->broadcastStatusChanges])));
        $this->components->twoColumnDetail('Presence', $settings->presence ? 'on' : 'off');
        $this->components->twoColumnDetail('Public sections', $public ? implode(', ', $public) : 'none');

        return self::SUCCESS;
    }
}
