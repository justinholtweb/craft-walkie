<?php

declare(strict_types=1);

use JustinHoltWeb\Walkie\Client\EchoConfig;

$reverb = [
    'driver' => 'reverb', 'key' => 'pub-key', 'secret' => 'top-secret', 'app_id' => '1234',
    'options' => ['host' => 'ws.example.com', 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
];

it('builds Reverb options for Echo', function () use ($reverb) {
    expect(EchoConfig::fromConnection($reverb))->toBe([
        'broadcaster' => 'reverb', 'key' => 'pub-key', 'forceTLS' => true,
        'wsHost' => 'ws.example.com', 'wsPort' => 443, 'wssPort' => 443, 'enabledTransports' => ['wss', 'ws'],
    ]);
});

it('never hands a browser the secret or app id', function () use ($reverb) {
    $json = json_encode(EchoConfig::fromConnection($reverb));
    expect($json)->not->toContain('top-secret')->not->toContain('1234');
});

it('lets the browser host and port differ from the server side', function () use ($reverb) {
    $config = EchoConfig::fromConnection($reverb, 'public.example.com', 8080);
    expect($config['wsHost'])->toBe('public.example.com')->and($config['wsPort'])->toBe(8080);
});

it('uses the cluster for hosted Pusher, not its REST host', function () {
    $config = EchoConfig::fromConnection(['driver' => 'pusher', 'key' => 'k', 'secret' => 's', 'app_id' => 'a',
        'options' => ['cluster' => 'eu', 'host' => 'api-eu.pusher.com', 'port' => 443, 'scheme' => 'https']]);
    expect($config)->toBe(['broadcaster' => 'pusher', 'key' => 'k', 'forceTLS' => true, 'cluster' => 'eu']);
});

it('keeps a self-hosted Pusher-protocol host (Soketi)', function () {
    $config = EchoConfig::fromConnection(['driver' => 'pusher', 'key' => 'k', 'options' => ['host' => 'soketi', 'port' => 6001, 'scheme' => 'http']]);
    expect($config['wsHost'])->toBe('soketi')->and($config['wsPort'])->toBe(6001)->and($config['forceTLS'])->toBeFalse();
});

it('has nothing for drivers a browser cannot reach', function (?array $connection) {
    expect(EchoConfig::fromConnection($connection))->toBeNull();
})->with([
    'log' => [['driver' => 'log']],
    'null' => [['driver' => 'null']],
    'redis' => [['driver' => 'redis', 'connection' => 'default']],
    'ably (its key is a secret)' => [['driver' => 'ably', 'key' => 'app.key:secret']],
    'reverb without a key' => [['driver' => 'reverb', 'key' => null, 'options' => ['host' => 'x']]],
    'reverb without a host' => [['driver' => 'reverb', 'key' => 'k', 'options' => []]],
    'missing' => [null],
]);
