<?php

declare(strict_types=1);

use JustinHoltWeb\Walkie\Changes\Change;

function change(string $event = 'saved', ?string $status = 'live', ?string $previous = 'live', bool $public = true, string $type = 'entry'): Change
{
    return new Change($event, $type, 12, 1, 'default', 'news', $status, $previous, $public, 3, '2026-11-18T10:00:00+00:00');
}

it('keys a change by element and site', function () {
    expect(change()->key())->toBe('entry:12:1');
});

it('carries ids and statuses only on private channels', function () {
    expect(change()->privatePayload())->toBe([
        'event' => 'saved', 'id' => 12, 'type' => 'entry', 'site' => 'default', 'section' => 'news',
        'status' => 'live', 'previousStatus' => 'live', 'causedBy' => 3, 'dateUpdated' => '2026-11-18T10:00:00+00:00',
    ]);
    expect(change(type: 'asset')->privatePayload())->toHaveKey('volume')->not->toHaveKey('section');
});

it('tells guests even less', function () {
    expect(change()->publicPayload())->toBe(['event' => 'saved', 'id' => 12, 'type' => 'entry', 'site' => 'default', 'section' => 'news']);
});

it('only tells guests about live entries of public sections, or ones that just stopped being live', function (string $event, ?string $status, ?string $previous, bool $public, string $type, bool|string $expected) {
    $c = change($event, $status, $previous, $public, $type);
    expect($c->isPublicNews() ? $c->publicEvent() : false)->toBe($expected);
})->with([
    'live save' => ['saved', 'live', 'live', true, 'entry', 'saved'],
    'published' => ['saved', 'live', 'pending', true, 'entry', 'saved'],
    'new and live' => ['created', 'live', null, true, 'entry', 'saved'],
    'disabled' => ['saved', 'disabled', 'live', true, 'entry', 'removed'],
    'expired' => ['status', 'expired', 'live', true, 'entry', 'removed'],
    'deleted while live' => ['deleted', null, 'live', true, 'entry', 'removed'],
    'pending save' => ['saved', 'pending', 'pending', true, 'entry', false],
    'new but disabled' => ['created', 'disabled', null, true, 'entry', false],
    'deleted while disabled' => ['deleted', null, 'disabled', true, 'entry', false],
    'pending → disabled' => ['saved', 'disabled', 'pending', true, 'entry', false],
    'private section' => ['saved', 'live', 'live', false, 'entry', false],
    'assets never' => ['saved', 'enabled', 'enabled', true, 'asset', false],
]);

it('merges repeated changes: first previous status, last status, strongest event', function () {
    $first = change('saved', 'live', 'pending');
    $merged = $first->merge(change('saved', 'disabled', 'live'));
    expect($merged->previousStatus)->toBe('pending')
        ->and($merged->status)->toBe('disabled')
        ->and($merged->event)->toBe('saved');

    expect(change('created', 'live', null)->merge(change('saved', 'live', 'live'))->event)->toBe('created');
    expect(change('saved')->merge(change('deleted', null, 'live'))->event)->toBe('deleted');
    expect(change('deleted', null, 'live')->merge(change('restored', 'live', null))->event)->toBe('restored');
});
