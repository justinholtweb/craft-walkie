---
title: Installation
slug: installation
order: 10
summary: Requirements, the Craft 6 beta caution, broadcasting setup, and a first live page.
---

> **Craft 6 only, and still in beta.** Walkie is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Walkie (`6.0.0-beta.1`). Try it on development and staging sites first, and **use caution
> before running it on a production site**: keep backups, and expect things to change between betas.
> It does not run on Craft 5.

## Requirements

- Craft CMS 6.0.0-beta.1 or later, PHP 8.5.
- A broadcast connection browsers can reach: **Laravel Reverb** (self-hosted, free) or **Pusher** (or any
  Pusher-protocol server, e.g. Soketi). Both need `pusher/pusher-php-server`.
- Laravel Echo and `pusher-js` on the front end (your own build, or a CDN you choose).

## 1. Install the plugin

```bash
composer require justinholtweb/craft-walkie
php artisan craft:plugin:install walkie
```

## 2. Set up broadcasting

Walkie uses your app's broadcasting configuration; it never talks to Reverb or Pusher on its own.

```bash
php artisan install:broadcasting     # Reverb: installs laravel/reverb, writes REVERB_* to .env
php artisan reverb:start             # in production, keep this running (Supervisor, Forge daemon…)
```

For Pusher, set `BROADCAST_CONNECTION=pusher` and `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`,
`PUSHER_APP_CLUSTER`, then `composer require pusher/pusher-php-server`.

A stock Craft 6 app has no `/broadcasting/auth` route and no `routes/channels.php`. Walkie registers
`POST /broadcasting/auth` itself (Craft's session, CSRF-protected). If your app registers its own
(`withBroadcasting()` in `bootstrap/app.php`), yours wins and Walkie adds its channels to it.

## 3. Queue and scheduler

Broadcasts are queued by default so a slow WebSocket server never slows down saving: run a queue worker.
Craft fires no event when a post date or expiry date passes, so `craft:walkie:scheduled` runs every minute
from Laravel's scheduler (`php artisan schedule:work`, or the usual cron).

## 4. Choose what's public

Settings → Walkie → **Public sections**: live entries in these sections are announced on public channels
anyone can subscribe to (ids only). Everything else needs a signed-in user Craft lets view it.

## 5. A first live page

```twig
{# in your layout, after Laravel Echo is loaded as window.Echo (or window.Echo = Echo class + window.Pusher) #}
{{ walkie.script() }}

{# on an entry page #}
<script type="module">
  addEventListener('walkie:ready', ({ detail: walkie }) => {
    walkie.on({ id: {{ entry.id }} }, 'saved', () => location.reload());
    walkie.on({ id: {{ entry.id }} }, 'removed', () => location.reload());
  });
</script>
```

Check the wiring with `php artisan craft:walkie:status`.
