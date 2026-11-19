<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Gql;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Resolvers\Elements\Entry as EntryResolver;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sections;
use GraphQL\Error\UserError;
use JustinHoltWeb\Walkie\Channels\ChannelNames;
use JustinHoltWeb\Walkie\Settings;
use JustinHoltWeb\Walkie\Template\WalkieApi;

class ChannelsResolver
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function resolve(mixed $source, array $args): array
    {
        if (! GqlHelper::canSchema(ChannelsQuery::COMPONENT)) {
            throw new UserError('This schema can’t read Walkie’s channels.');
        }

        $site = self::site(isset($args['site']) ? (string) $args['site'] : null);
        $settings = app(Settings::class);
        $sections = [];

        if ($site->enabled) {
            foreach ($settings->publicSectionUids() as $uid) {
                $section = Sections::getSectionByUid($uid);

                if ($section !== null && isset($section->getSiteSettings()[$site->id]) && GqlHelper::canSchema("sections.$uid")) {
                    $sections[] = ['section' => $section->handle, 'channel' => ChannelNames::feed($site->handle, 'entry', $section->handle)];
                }
            }
        }

        return [
            'site' => $site->handle,
            'sections' => $sections,
            'echo' => app(WalkieApi::class)->echoConfig(),
            '_site' => $site,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $args
     */
    public static function entryChannel(array $source, array $args): ?string
    {
        $site = $source['_site'] ?? null;

        if (! $site instanceof Site || ! $site->enabled || ! GqlHelper::canQueryEntries()) {
            return null;
        }

        // Craft's own entries resolver applies the schema's section scope; live only.
        $entry = EntryResolver::prepareQuery(null, [])->site($site->handle)->id((int) $args['id'])->status('live')->one();

        if (! $entry instanceof Entry || ! ($section = $entry->getSection()) || ! app(Settings::class)->isPublicSection($section->uid)) {
            return null;
        }

        return ChannelNames::element($site->handle, 'entry', (int) $entry->id);
    }

    /** The requested site, or the primary one the schema can read. */
    private static function site(?string $handle): Site
    {
        $allowed = GqlHelper::getAllowedSites();

        foreach ($allowed as $site) {
            if ($handle === null ? $site->primary : $site->handle === $handle) {
                return $site;
            }
        }

        if ($handle === null && $allowed !== []) {
            return $allowed[0];
        }

        throw new UserError($handle === null ? 'This schema can’t read any site.' : "This schema can’t read the “{$handle}” site.");
    }
}
