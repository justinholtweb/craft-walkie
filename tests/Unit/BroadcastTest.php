<?php

declare(strict_types=1);

use Illuminate\Broadcasting\PrivateChannel;
use JustinHoltWeb\Walkie\Broadcasting\WalkieBroadcast;

it('carries its delivery options', function () {
    $b = new WalkieBroadcast('saved', [new PrivateChannel('walkie.default.entry.1')], ['id' => 1], now: true, queueName: 'walkie', connection: 'reverb');

    expect($b->broadcastAs())->toBe('saved')
        ->and($b->broadcastWith())->toBe(['id' => 1])
        ->and($b->shouldBroadcastNow())->toBeTrue()
        ->and($b->broadcastQueue())->toBe('walkie')
        ->and($b->broadcastConnections())->toBe(['reverb'])
        ->and((string) $b->broadcastOn()[0])->toBe('private-walkie.default.entry.1');
});

it('uses the default connection when none is set', function () {
    expect((new WalkieBroadcast('saved', [], []))->broadcastConnections())->toBe([null]);
});
