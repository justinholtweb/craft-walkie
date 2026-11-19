# Walkie

Real-time Craft: entry and asset saves, deletes and status changes broadcast over Laravel Reverb or Pusher,
on channels that respect Craft permissions.

> [!WARNING]
> **Craft 6 only, and in beta.** Walkie is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Walkie (`6.0.0-beta.1`). Try it on development and staging sites first and **use caution on
> production**: keep backups, and expect changes between betas. It does not run on Craft 5.

Docs: [justinholt.com/plugins/craft-walkie](https://justinholt.com/plugins/craft-walkie/docs/). See [PLAN.md](PLAN.md).

## Why

Craft 6 already tells *your own* other control panel tabs when you save something (core's
`BroadcastElementChanges`). Nobody else hears about it: not a colleague with the same entry open, not the
front end showing a live blog or a scoreboard. Walkie is that layer, built on Laravel broadcasting:

- **Element events → broadcasts.** Saves, deletes, restores and scheduled publish/expiry, per site, once per
  element per request, sent after the database transaction commits. Drafts, revisions and bulk resaves never are.
- **Channels that respect Craft permissions.** Private channels are authorised with Craft's own element
  policies (`viewEntries`, `viewPeerEntries`, `viewAssets`, `editSite`…). Public channels exist only for
  sections you switch on, and only ever announce **live** entries.
- **Minimal payloads.** Ids, site, section and status. Never titles, fields or URLs: clients refetch through
  their own permission-checked requests.
- **Presence**: who else is looking at this entry.
- **Twig, Blade, a tiny JS client and GraphQL** to wire up a front end.

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- A Laravel broadcast connection browsers can reach: [Reverb](https://reverb.laravel.com) (self-hosted) or
  Pusher (or a Pusher-protocol server such as Soketi), with `pusher/pusher-php-server`. Laravel Echo on the front end.

## Setup

1. Configure broadcasting as in any Laravel 13 app: `php artisan install:broadcasting` (Reverb), or set
   `BROADCAST_CONNECTION=pusher` and the `PUSHER_*` keys. Stock Craft 6 apps have no broadcast routes;
   Walkie registers `POST /broadcasting/auth` for you (and leaves an app's own one alone).
2. Settings → Walkie: pick what to broadcast, which sections are public, and (optionally) the connection.
3. Run a queue worker (broadcasts are queued by default) and Laravel's scheduler (for scheduled
   publish/expiry: `craft:walkie:scheduled` runs every minute).
4. On the front end:

```twig
{# Loads Laravel Echo yourself (npm laravel-echo + pusher-js), then: #}
{{ walkie.script() }}
<script type="module">
  addEventListener('walkie:ready', ({ detail: walkie }) => {
    walkie.on({ id: {{ entry.id }} }, 'saved', () => location.reload());
    walkie.onSection('news', 'saved', ({ id }) => refreshList(id));
  });
</script>
```

```blade
{{ $walkie->script() }}
```

`php artisan craft:walkie:status` shows the connection, the auth route and the public sections.

## Channels

| Channel | Who can subscribe | What it carries |
|---|---|---|
| `walkie.{site}.entry.{id}` | anyone, if the section is public | `saved` / `removed` while the entry is (or just stopped being) live |
| `walkie.{site}.entries.{section}` | anyone, if the section is public | the same, for every entry in the section |
| `private-walkie.{site}.entry.{id}` | users Craft lets view the entry in that site | `created`, `saved`, `restored`, `status`, `deleted` |
| `private-walkie.{site}.entries.{section}` | `viewEntries` + `viewPeerEntries` (+ `editSite`) | the same, for the section |
| `private-walkie.{site}.asset.{id}`, `…assets.{volume}` | `viewAssets` + `viewPeerAssets` (+ `editSite`) | asset changes |
| `presence-walkie.{site}.entry.{id}` | as the private channel, when presence is on | who's here: `{id, name}` |

Unknown sites, sections, volumes, ids, drafts and revisions are refused. See [docs/channels.md](docs/channels.md).

## For other plugins

`app(\JustinHoltWeb\Walkie\Walkie::class)->send($entry, 'liveUpdate', ['updateId' => 7])` broadcasts your own
event on an element's channels, to exactly the people who may view it. See [docs/events.md](docs/events.md).

## Development

```bash
composer install
composer test               # Pest
composer phpstan            # Larastan level 6
composer check-cs           # Pint
node --test tests/js        # the JS client against a fake Echo
tests/Harness/check.sh      # live checks in the Craft 6 harness (WALKIE_MULTISITE=1 adds a second site)
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` against Laravel's real Pusher broadcaster
(auth signatures verified), with broadcasts captured to a file instead of sent: no Reverb server was involved.

## License

Free, under the [Craft License](LICENSE.md).
