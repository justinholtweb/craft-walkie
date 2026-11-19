<?php

declare(strict_types=1);

use JustinHoltWeb\Walkie\Settings;

it('has safe defaults', function () {
    $s = new Settings;

    expect($s->broadcastSaves)->toBeTrue()
        ->and($s->presence)->toBeFalse()
        ->and($s->publicSectionUids())->toBe([])
        ->and($s->registerAuthRoute)->toBeTrue();
});

it('reads public sections from a list or a string, ignoring “all”', function () {
    $s = new Settings;
    $s->publicSections = ['a-uid', '*', 'b-uid'];
    expect($s->publicSectionUids())->toBe(['a-uid', 'b-uid'])->and($s->isPublicSection('a-uid'))->toBeTrue();

    $s->publicSections = 'a-uid, c-uid';
    expect($s->publicSectionUids())->toBe(['a-uid', 'c-uid']);

    $s->publicSections = null;
    expect($s->publicSectionUids())->toBe([])->and($s->isPublicSection('a-uid'))->toBeFalse();
});
