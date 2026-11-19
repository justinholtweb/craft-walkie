<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use JustinHoltWeb\Walkie\Channels\ChannelAuthorizer;
use JustinHoltWeb\Walkie\Channels\ChannelNames;
use JustinHoltWeb\Walkie\Settings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel Echo's channel auth endpoint. Walkie's channels are checked against Walkie's
 * broadcast connection; anything else is passed to the app's default broadcaster and
 * whatever channels the app registered there.
 */
class AuthController
{
    public function __invoke(Request $request, ChannelAuthorizer $authorizer, Settings $settings): Response
    {
        $channel = $request->input('channel_name');

        if (! is_string($channel) || $channel === '' || ! is_string($request->input('socket_id'))) {
            abort(403);
        }

        $walkie = ChannelNames::isWalkie($channel);
        $broadcaster = Broadcast::connection($walkie ? ($settings->connection ?: null) : null);

        if ($walkie) {
            $authorizer->register($broadcaster);
        }

        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        $result = $broadcaster->auth($request);

        // The log and null broadcasters authorise nothing; say no rather than an empty 200.
        if ($result === null || $result === '') {
            abort(403);
        }

        return $result instanceof Response ? $result : new JsonResponse($result);
    }
}
