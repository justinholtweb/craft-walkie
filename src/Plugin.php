<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Override;

use function CraftCms\Cms\t;

/**
 * Walkie — Real-time Craft: element saves, deletes and status changes broadcast over Reverb or Pusher, with channels that respect Craft permissions.
 *
 * See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        return Ui::make([
            Field::make(t('Broadcast saves', category: 'walkie'), Lightswitch::make('broadcastSaves'))
                ->instructions(t('Broadcast when an element is saved.', category: 'walkie')),
            Field::make(t('Broadcast deletes', category: 'walkie'), Lightswitch::make('broadcastDeletes'))
                ->instructions(t('Broadcast when an element is deleted.', category: 'walkie')),
            Field::make(t('Presence channels', category: 'walkie'), Lightswitch::make('presence'))
                ->instructions(t('Track who is viewing each element (for "who\'s here" indicators).', category: 'walkie')),
        ]);
    }
}
