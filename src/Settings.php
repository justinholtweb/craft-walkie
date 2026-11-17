<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Broadcast saves. Broadcast when an element is saved. */
    public bool $broadcastSaves = true;

    /** Broadcast deletes. Broadcast when an element is deleted. */
    public bool $broadcastDeletes = true;

    /** Presence channels. Track who is viewing each element (for "who's here" indicators). */
    public bool $presence = false;
}
