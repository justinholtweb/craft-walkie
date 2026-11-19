<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Template;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Volumes;
use Illuminate\Support\HtmlString;
use JustinHoltWeb\Walkie\Channels\ChannelNames;
use JustinHoltWeb\Walkie\Client\EchoConfig;
use JustinHoltWeb\Walkie\Plugin;
use JustinHoltWeb\Walkie\Settings;
use JustinHoltWeb\Walkie\Walkie;

use function CraftCms\Cms\currentUser;

/**
 * The `walkie` template global, the same object in Twig (`walkie.channel(entry)`) and
 * Blade (`$walkie->channel($entry)`).
 *
 * Channel names come back without Echo's `private-`/`presence-` prefixes: pass them to
 * `Echo.channel()`, `Echo.private()` or `Echo.join()`. Anything unknown (site, section,
 * volume, element type) returns null rather than a name nobody will ever broadcast on.
 */
class WalkieApi
{
    public function __construct(
        private readonly Walkie $walkie,
        private readonly Settings $settings,
    ) {}

    /** The element's public channel, or null unless it's an entry in a public section. */
    public function channel(?ElementInterface $element): ?string
    {
        $type = $element ? $this->walkie->typeOf($element) : null;

        if ($element === null || $type !== 'entry' || ! $this->walkie->isPublicGroup($element) || ! $site = $this->siteOf($element)) {
            return null;
        }

        return ChannelNames::element($site->handle, $type, (int) $element->id);
    }

    /** The element's private channel (subscribers need permission to view it). */
    public function privateChannel(?ElementInterface $element): ?string
    {
        $type = $element ? $this->walkie->typeOf($element) : null;

        if ($element === null || $type === null || ! $element->id || $element->getIsDraft() || $element->getIsRevision() || ! $site = $this->siteOf($element)) {
            return null;
        }

        return ChannelNames::element($site->handle, $type, (int) $element->id);
    }

    /** The element's presence channel, when presence is switched on. */
    public function presenceChannel(?ElementInterface $element): ?string
    {
        return $this->settings->presence ? $this->privateChannel($element) : null;
    }

    /**
     * A section's feed. Public (`private: false`) only for sections switched on in
     * “Public sections”; private feeds need `viewEntries` + `viewPeerEntries`.
     */
    public function sectionChannel(string $section, ?string $site = null, bool $private = false): ?string
    {
        $section = Sections::getSectionByHandle($section);
        $site = $this->site($site);

        if ($section === null || $site === null || ! isset($section->getSiteSettings()[$site->id])) {
            return null;
        }

        if (! $private && (! $site->enabled || ! $this->settings->isPublicSection($section->uid))) {
            return null;
        }

        return ChannelNames::feed($site->handle, 'entry', $section->handle);
    }

    /** A volume's private feed (`viewAssets` + `viewPeerAssets`). */
    public function volumeChannel(string $volume, ?string $site = null): ?string
    {
        $volume = Volumes::getVolumeByHandle($volume);
        $site = $this->site($site);

        return $volume && $site ? ChannelNames::feed($site->handle, 'asset', $volume->handle) : null;
    }

    /**
     * What the JS client needs: the site, Echo options for the configured connection
     * (public key and host only), the auth endpoint, and — for signed-in visitors, who
     * already have a session — a CSRF token for it.
     *
     * @return array<string, mixed>|null
     */
    public function config(?string $site = null): ?array
    {
        $site = $this->site($site);

        if ($site === null) {
            return null;
        }

        $echo = $this->echoConfig();
        $signedIn = currentUser() !== null;

        return [
            'site' => $site->handle,
            'prefix' => ChannelNames::PREFIX,
            'presence' => $this->settings->presence,
            'signedIn' => $signedIn,
            'authEndpoint' => url('broadcasting/auth'),
            'csrfToken' => $signedIn ? csrf_token() : null,
            'echo' => $echo,
        ];
    }

    /**
     * Laravel Echo options for Walkie's connection: public key and host only.
     *
     * @return array<string, mixed>|null null for drivers browsers can't connect to
     */
    public function echoConfig(): ?array
    {
        $name = $this->settings->connection ?: config('broadcasting.default');
        $connection = is_string($name) ? config("broadcasting.connections.$name") : null;

        return EchoConfig::fromConnection(is_array($connection) ? $connection : null, $this->settings->clientHost, $this->settings->clientPort);
    }

    /**
     * `<script>` tags that load Walkie's client and start it as `window.walkie`, using the
     * page's `window.Echo` (an instance, or the class plus `window.Pusher`).
     */
    public function script(?string $site = null): ?HtmlString
    {
        $config = $this->config($site);

        if ($config === null) {
            return null;
        }

        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $src = htmlspecialchars(Plugin::getInstance()->asset('walkie.js').'?v='.substr(md5((string) Plugin::getInstance()->version), 0, 8), ENT_QUOTES);

        return new HtmlString("<script>window.walkieConfig = {$json};</script>\n<script type=\"module\" src=\"{$src}\"></script>");
    }

    private function site(?string $handle): ?Site
    {
        if ($handle === null) {
            return Sites::getCurrentSite();
        }

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $handle) ? Sites::getSiteByHandle($handle, true) : null;
    }

    private function siteOf(ElementInterface $element): ?Site
    {
        return Sites::getSiteById($element->siteId, true);
    }
}
