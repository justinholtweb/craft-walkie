<?php

// Walkie harness fixtures (idempotent). Everything is prefixed `walkie`.
//   volume    walkieFiles (public disk, walkie-files/) with walkie.txt
//   sections  walkieNews (public in the checks), walkieSecret (never granted to anyone)
//   entries   walkie-live, walkie-off (disabled), walkie-future (pending), walkie-own (by walkie_own),
//             walkie-secret (walkieSecret)
//   users     walkie_viewer  viewEntries + viewPeerEntries on walkieNews, editSite on default
//             walkie_own     viewEntries on walkieNews only (sees its own entries, not the feed)
//             walkie_none    no permissions (a front-end member)
//             walkie_assets  viewAssets + viewPeerAssets on walkieFiles
//             walkie_all     like walkie_viewer, plus editSite on every site
//   password  walkiepassword
// Run with: ddev artisan tinker --execute "require 'storage/walkie-fixtures.php';"

use CraftCms\Cms\Asset\Data\Volume;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Validation\AssetRules;
use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\UserPermissions;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$type = EntryTypes::getEntryTypeByHandle('walkieNewsType');
if (! $type) {
    $type = new EntryType;
    $type->name = 'Walkie News';
    $type->handle = 'walkieNewsType';
    EntryTypes::saveEntryType($type) || throw new RuntimeException('entry type: '.json_encode($type->errors()->all()));
}

$sections = [];
foreach (['walkieNews' => 'Walkie News', 'walkieSecret' => 'Walkie Secret'] as $handle => $name) {
    $section = Sections::getSectionByHandle($handle) ?? new Section;
    $section->name = $name;
    $section->handle = $handle;
    $section->type = SectionType::Channel;
    $section->maxAuthors = 1;
    $settings = [];
    // The primary site, plus Walkie's own walkiefr when the multisite checks add it (never other agents' sites).
    foreach (Sites::getAllSites(true)->filter(fn ($site) => $site->primary || $site->handle === 'walkiefr') as $site) {
        $s = new SectionSiteSettings;
        $s->siteId = $site->id;
        $s->hasUrls = false;
        $s->enabledByDefault = true;
        $settings[$site->id] = $s;
    }
    $section->setSiteSettings($settings);
    $section->setEntryTypes([$type]);
    Sections::saveSection($section) || throw new RuntimeException("section $handle: ".json_encode($section->errors()->all()));
    $sections[$handle] = Sections::getSectionByHandle($handle);
}

// Walkie's own volume (on the public disk, under walkie-files/) with one asset.
$volume = Volumes::getVolumeByHandle('walkieFiles');
if (! $volume) {
    $volume = new Volume;
    $volume->name = 'Walkie Files';
    $volume->handle = 'walkieFiles';
    $volume->fsHandle = 'public';
    $volume->subpath = 'walkie-files';
    Volumes::saveVolume($volume) || throw new RuntimeException('volume: '.json_encode($volume->errors()->all()));
    $volume = Volumes::getVolumeByHandle('walkieFiles');
}
$asset = Asset::find()->volume('walkieFiles')->filename('walkie.txt')->one();
if (! $asset) {
    $tmp = tempnam(sys_get_temp_dir(), 'walkie');
    file_put_contents($tmp, 'walkie');
    $asset = new Asset;
    $asset->ruleset->useScenario(AssetRules::SCENARIO_CREATE);
    $asset->tempFilePath = $tmp;
    $asset->setFilename('walkie.txt');
    $asset->newFolderId = Folders::getRootFolderByVolumeId($volume->id)->id;
    $asset->setVolumeId($volume->id);
    Elements::saveElement($asset) || throw new RuntimeException('asset: '.json_encode($asset->errors()->all()));
}

$users = [];
$images = $volume;
$news = $sections['walkieNews']->uid;
$sites = Sites::getAllSites(true)->filter(fn ($site) => $site->primary || $site->handle === 'walkiefr');
$primary = Sites::getPrimarySite();
$perms = [
    'walkie_viewer' => ['accessCp', "viewEntries:$news", "viewPeerEntries:$news", "editSite:$primary->uid"],
    'walkie_own' => ['accessCp', "viewEntries:$news", "editSite:$primary->uid"],
    'walkie_none' => [],
    'walkie_assets' => ['accessCp', "viewAssets:$images->uid", "viewPeerAssets:$images->uid", "editSite:$primary->uid"],
    'walkie_all' => ['accessCp', "viewEntries:$news", "viewPeerEntries:$news", ...$sites->map(fn ($s) => "editSite:$s->uid")->all()],
];
foreach ($perms as $username => $permissions) {
    $user = User::find()->username($username)->status(null)->one();
    if (! $user) {
        $user = new User;
        $user->username = $username;
        $user->email = "$username@walkie.test";
        $user->firstName = ucfirst(str_replace('walkie_', '', $username));
        $user->lastName = 'Walkie';
        $user->newPassword = 'walkiepassword';
        Elements::saveElement($user) || throw new RuntimeException("user $username: ".json_encode($user->errors()->all()));
        Users::activateUser($user);
    }
    UserPermissions::saveUserPermissions($user->id, $permissions);
    // editSite:* is only a registered permission while the install is multi-site, so Craft drops it
    // on a single-site install. Other sessions add and remove sites at any time: store it anyway.
    foreach (array_filter($permissions, fn ($p) => str_starts_with($p, 'editSite:')) as $name) {
        $pid = DB::table('userpermissions')->whereIn('name', [$name, strtolower($name)])->value('id')
            ?? DB::table('userpermissions')->insertGetId(['name' => $name, 'dateCreated' => now(), 'dateUpdated' => now(), 'uid' => (string) Str::uuid()]);
        DB::table('userpermissions_users')->updateOrInsert(['permissionId' => $pid, 'userId' => $user->id], ['dateCreated' => now(), 'dateUpdated' => now(), 'uid' => (string) Str::uuid()]);
    }
    $users[$username] = $user->id;
}

$entries = [];
$plan = [
    'walkie-live' => ['walkieNews', true, '-1 hour', $users['walkie_viewer']],
    'walkie-off' => ['walkieNews', false, '-1 hour', $users['walkie_viewer']],
    'walkie-future' => ['walkieNews', true, '+1 day', $users['walkie_viewer']],
    'walkie-own' => ['walkieNews', true, '-1 hour', $users['walkie_own']],
    'walkie-secret' => ['walkieSecret', true, '-1 hour', $users['walkie_viewer']],
];
foreach ($plan as $slug => [$sectionHandle, $enabled, $post, $author]) {
    $entry = Entry::find()->slug($slug)->status(null)->site('*')->unique()->one() ?? new Entry;
    $entry->sectionId = $sections[$sectionHandle]->id;
    $entry->typeId = $type->id;
    $entry->siteId = $primary->id;
    $entry->title = ucwords(str_replace('-', ' ', $slug));
    $entry->slug = $slug;
    $entry->enabled = $enabled;
    $entry->postDate = new DateTime($post);
    $entry->expiryDate = null;
    $entry->setAuthorIds([$author]);
    Elements::saveElement($entry) || throw new RuntimeException("entry $slug: ".json_encode($entry->errors()->all()));
    $entries[$slug] = $entry->id;
}

echo json_encode(['asset' => $asset->id, 'entries' => $entries, 'users' => $users, 'news' => $news, 'secret' => $sections['walkieSecret']->uid]);
