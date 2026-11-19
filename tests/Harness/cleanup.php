<?php

// Removes every Walkie harness fixture: entries, sections, entry type, the walkieFiles volume and its asset, users, GraphQL schemas
// and tokens, and the walkiefr site. Never touches anything not prefixed `walkie`.
// Run with: ddev artisan tinker --execute "require 'storage/walkie-cleanup.php';"

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Elements\User;

foreach (['walkieNews', 'walkieSecret'] as $handle) {
    if ($section = Sections::getSectionByHandle($handle)) {
        foreach (Entry::find()->sectionId($section->id)->status(null)->site('*')->unique()->all() as $entry) {
            Elements::deleteElement($entry, true);
        }
        Sections::deleteSection($section);
    }
}

if ($volume = Volumes::getVolumeByHandle('walkieFiles')) {
    foreach (Asset::find()->volumeId($volume->id)->status(null)->all() as $asset) {
        Elements::deleteElement($asset, true);
    }
    Volumes::deleteVolume($volume);
}

if ($type = EntryTypes::getEntryTypeByHandle('walkieNewsType')) {
    EntryTypes::deleteEntryType($type);
}

foreach (User::find()->username(['walkie_viewer', 'walkie_own', 'walkie_none', 'walkie_assets', 'walkie_all'])->status(null)->all() as $user) {
    Elements::deleteElement($user, true);
}

foreach (Gql::getTokens() as $token) {
    if (str_starts_with((string) $token->name, 'walkie_')) {
        Gql::deleteTokenById($token->id);
    }
}
foreach (Gql::getSchemas() as $schema) {
    if (str_starts_with((string) $schema->name, 'walkie_')) {
        Gql::deleteSchema($schema);
    }
}

if ($fr = Sites::getSiteByHandle('walkiefr', true)) {
    Sites::deleteSite($fr);
}

echo 'ok';
