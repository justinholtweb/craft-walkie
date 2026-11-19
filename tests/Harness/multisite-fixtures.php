<?php

// Walkie harness: Walkie's own second site, `walkiefr` (French), with the Walkie sections
// on it (by re-running fixtures.php). Never touches a site Walkie didn't create.
// cleanup.php removes it. Run under bin/harness-lock:
//   ddev artisan tinker --execute "require 'storage/walkie-multisite-fixtures.php';"

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;

$fr = Sites::getSiteByHandle('walkiefr', true);

if (! $fr) {
    $primary = Sites::getPrimarySite();
    $fr = new Site;
    $fr->groupId = $primary->groupId;
    $fr->name = 'Walkie Français';
    $fr->handle = 'walkiefr';
    $fr->language = 'fr-FR';
    $fr->hasUrls = true;
    $fr->baseUrl = '@web/walkiefr/';
    $fr->enabled = true;
    Sites::saveSite($fr) || throw new RuntimeException('site: '.json_encode($fr->errors()->all()));
}

ob_start();
require __DIR__.'/walkie-fixtures.php';
ob_end_clean();

echo 'ok';
