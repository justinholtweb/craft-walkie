<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Listeners;

use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use JustinHoltWeb\Walkie\Template\WalkieApi;

/** `walkie` in Twig, `$walkie` in Blade: one object, both engines. */
class RegisterTemplateApi
{
    public function handle(TemplateGlobalsResolving $event): void
    {
        $event->globals['walkie'] = app(WalkieApi::class);
    }
}
