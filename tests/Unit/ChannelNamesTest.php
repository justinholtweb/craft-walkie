<?php

declare(strict_types=1);

use JustinHoltWeb\Walkie\Channels\ChannelNames;

it('names channels per site', function () {
    expect(ChannelNames::element('default', 'entry', 12))->toBe('walkie.default.entry.12')
        ->and(ChannelNames::feed('fr', 'entry', 'news'))->toBe('walkie.fr.entries.news')
        ->and(ChannelNames::feed('default', 'asset', 'images'))->toBe('walkie.default.assets.images');
});

it('recognises its own channels with or without Echo prefixes', function (string $channel, bool $expected) {
    expect(ChannelNames::isWalkie($channel))->toBe($expected);
})->with([
    ['walkie.default.entry.1', true],
    ['private-walkie.default.entry.1', true],
    ['presence-walkie.default.entry.1', true],
    ['private-encrypted-walkie.default.entry.1', false],
    ['private-App.Models.User.1', false],
    ['walkiex.default', false],
]);
