<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Walkie\Http\AuthController;
use JustinHoltWeb\Walkie\Plugin;

/*
 * Front-end routes (`craft` + `craft.web` middleware: Craft's session guard and CSRF).
 *
 * POST /broadcasting/auth — Laravel Echo's default auth endpoint. Stock Craft 6 apps don't
 * register one. An app that does (bootstrap/app.php `withBroadcasting()`) registers it later
 * and keeps it; Walkie's channels are then authorised there too.
 */
if (Plugin::getInstance()->getSettings()->registerAuthRoute) {
    Route::post('broadcasting/auth', AuthController::class)->name('walkie.auth');
}
