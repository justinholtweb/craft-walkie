<?php

// Walkie harness: GraphQL schemas + tokens (idempotent). Token = "walkie{name}" padded to 32 chars.
//   full       every site, the walkieNews + walkieSecret sections, walkie:read
//   nosection  every site and walkie:read, no section
//   nowalkie   every site and walkieNews, but not walkie:read
// Run with: ddev artisan tinker --execute "require 'storage/walkie-gql-fixtures.php';"

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Data\GqlToken;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;

$sites = Sites::getAllSites(true)->filter(fn ($s) => $s->primary || $s->handle === 'walkiefr')->map(fn ($s) => "sites.$s->uid:read")->all();
$news = 'sections.'.Sections::getSectionByHandle('walkieNews')->uid.':read';
$secret = 'sections.'.Sections::getSectionByHandle('walkieSecret')->uid.':read';

$schemas = [
    'full' => [...$sites, $news, $secret, 'walkie:read'],
    'nosection' => [...$sites, 'walkie:read'],
    'nowalkie' => [...$sites, $news],
];

foreach ($schemas as $name => $scope) {
    $schema = collect(Gql::getSchemas())->first(fn ($s) => $s->name === "walkie_{$name}") ?? new GqlSchema;
    $schema->name = "walkie_{$name}";
    $schema->scope = $scope;
    Gql::saveSchema($schema) || throw new RuntimeException(json_encode($schema->errors()->all()));

    $token = collect(Gql::getTokens())->first(fn ($t) => $t->name === "walkie_{$name}") ?? new GqlToken;
    $token->name = "walkie_{$name}";
    $token->schemaId = $schema->id;
    $token->accessToken = str_pad("walkie{$name}", 32, '0');
    Gql::saveToken($token) || throw new RuntimeException(json_encode($token->errors()->all()));
}

Gql::invalidateCaches();
echo 'ok';
