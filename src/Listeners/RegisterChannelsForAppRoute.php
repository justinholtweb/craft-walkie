<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Walkie\Channels\ChannelAuthorizer;
use JustinHoltWeb\Walkie\Http\AuthController;
use Throwable;

/**
 * When the app has its own `/broadcasting/auth` (Laravel's `withBroadcasting()`), it
 * authorises against the default broadcaster: give that broadcaster Walkie's channels,
 * just for that request. Channels are registered lazily, so a misconfigured broadcaster
 * can never break unrelated requests.
 */
class RegisterChannelsForAppRoute
{
    public function __construct(private readonly ChannelAuthorizer $authorizer) {}

    public function handle(RouteMatched $event): void
    {
        if (ltrim($event->route->uri(), '/') !== 'broadcasting/auth' || $event->route->getControllerClass() === AuthController::class) {
            return;
        }

        try {
            $this->authorizer->register(Broadcast::driver());
        } catch (Throwable $e) {
            Log::warning("Walkie couldn’t register its channels: {$e->getMessage()}");
        }
    }
}
