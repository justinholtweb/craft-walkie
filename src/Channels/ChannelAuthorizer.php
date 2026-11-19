<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Channels;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\User\Contracts\CraftUser;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Gate;
use JustinHoltWeb\Walkie\Settings;
use WeakMap;

/**
 * Decides who may subscribe to Walkie's private and presence channels, using Craft's own
 * permissions. Every check fails closed: an unknown site, section, volume or element, a
 * malformed id, a guest, or a missing permission all refuse the subscription.
 *
 * - element channels: the element must exist in that site (drafts, revisions and trashed
 *   elements never do) and Craft's element policy must let the user view it there
 *   (`viewEntries`/`viewPeerEntries`, `viewAssets`/`viewPeerAssets`, and `editSite` on
 *   multi-site installs).
 * - section feeds: `viewEntries` and, except for singles, `viewPeerEntries` (a feed shows
 *   everyone's entries), the section must be enabled for the site, plus `editSite`.
 * - volume feeds: `viewAssets` and `viewPeerAssets`, plus `editSite`.
 * - presence: only on element channels, only when presence is switched on.
 */
class ChannelAuthorizer
{
    /** @var WeakMap<Broadcaster, true> */
    private WeakMap $registered;

    public function __construct(private readonly Settings $settings)
    {
        $this->registered = new WeakMap;
    }

    /** Registers Walkie's channel callbacks with a broadcaster (once per broadcaster). */
    public function register(Broadcaster $broadcaster): void
    {
        if (isset($this->registered[$broadcaster]) || ! method_exists($broadcaster, 'channel')) {
            return;
        }

        $this->registered[$broadcaster] = true;
        // Craft's session guard, even when the app's own /broadcasting/auth runs on another guard.
        $options = ['guards' => [Cms::config()->getAuthGuard()]];

        $broadcaster->channel(ChannelNames::PATTERNS['entry'], fn ($user, string $site, string $id) => $this->element($user, Entry::class, $site, $id), $options);
        $broadcaster->channel(ChannelNames::PATTERNS['asset'], fn ($user, string $site, string $id) => $this->element($user, Asset::class, $site, $id), $options);
        $broadcaster->channel(ChannelNames::PATTERNS['entries'], fn ($user, string $site, string $section) => $this->section($user, $site, $section), $options);
        $broadcaster->channel(ChannelNames::PATTERNS['assets'], fn ($user, string $site, string $volume) => $this->volume($user, $site, $volume), $options);
    }

    /**
     * @param  class-string<ElementInterface>  $type
     * @return array{id: int, name: string}|bool
     */
    public function element(mixed $user, string $type, string $siteHandle, string $id, ?string $channel = null): array|bool
    {
        $site = $this->site($siteHandle);

        if (! $user instanceof CraftUser || $site === null || ! preg_match('/^[1-9]\d{0,9}$/', $id)) {
            return false;
        }

        $element = $type::find()->id((int) $id)->siteId($site->id)->status(null)->one();

        if (! $element instanceof ElementInterface || ! Gate::forUser($user)->allows('view', $element)) {
            return false;
        }

        if (! str_starts_with($channel ?? $this->requestedChannel(), 'presence-')) {
            return true;
        }

        if (! $this->settings->presence) {
            return false;
        }

        $person = $user->asElement();

        return [
            'id' => (int) $user->getCraftUserId(),
            'name' => (string) ($person->getFriendlyName() ?? $person->getName()),
        ];
    }

    public function section(mixed $user, string $siteHandle, string $handle, ?string $channel = null): bool
    {
        $site = $this->site($siteHandle);
        $section = Sections::getSectionByHandle($handle);

        if (! $user instanceof CraftUser || $site === null || $section === null || $this->isPresence($channel)) {
            return false;
        }

        return isset($section->getSiteSettings()[$site->id])
            && $user->can("viewEntries:$section->uid")
            && ($section->type === SectionType::Single || $user->can("viewPeerEntries:$section->uid"))
            && $this->canAccessSite($user, $site);
    }

    public function volume(mixed $user, string $siteHandle, string $handle, ?string $channel = null): bool
    {
        $site = $this->site($siteHandle);
        $volume = Volumes::getVolumeByHandle($handle);

        if (! $user instanceof CraftUser || $site === null || $volume === null || $this->isPresence($channel)) {
            return false;
        }

        return $user->can("viewAssets:$volume->uid")
            && $user->can("viewPeerAssets:$volume->uid")
            && $this->canAccessSite($user, $site);
    }

    private function site(string $handle): ?Site
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $handle) ? Sites::getSiteByHandle($handle, true) : null;
    }

    private function canAccessSite(CraftUser $user, Site $site): bool
    {
        return ! Sites::isMultiSite() || $user->can("editSite:$site->uid");
    }

    private function isPresence(?string $channel): bool
    {
        return str_starts_with($channel ?? $this->requestedChannel(), 'presence-');
    }

    private function requestedChannel(): string
    {
        $name = request()->input('channel_name');

        return is_string($name) ? $name : '';
    }
}
