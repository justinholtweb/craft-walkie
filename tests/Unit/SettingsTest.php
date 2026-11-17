<?php

declare(strict_types=1);

use JustinHoltWeb\Walkie\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->broadcastSaves)->toBe(true);
});
