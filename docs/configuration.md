---
title: Configuration
slug: configuration
order: 20
summary: Every setting, and overriding them per environment in config/craft/walkie.php.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

Settings → Walkie, or `config/craft/walkie.php` (which overrides the stored settings, per environment).

| Setting | Default | What it does |
|---|---|---|
| `broadcastSaves` | on | Broadcast entry and asset saves (`created`, `saved`). Drafts, revisions, duplicates and bulk resaves never are. |
| `broadcastDeletes` | on | Broadcast deletes and restores (`deleted`, `restored`). |
| `broadcastStatusChanges` | on | `craft:walkie:scheduled` broadcasts `status` when a post or expiry date passes. |
| `presence` | off | Allow presence channels (who else is viewing an element). |
| `publicSections` | none | Section UIDs whose **live** entries are announced on public channels. |
| `connection` | app default | A connection from `config/broadcasting.php`. Validated: an unknown name is refused. |
| `queueBroadcasts` | on | Send from the queue. Off sends during the request (a down server is logged, never fails the save). |
| `queue` | default | Queue name for broadcasts. |
| `registerAuthRoute` | on | Register `POST /broadcasting/auth` (an app's own route still wins). |
| `clientHost` / `clientPort` | from the connection | The WebSocket host/port browsers use, when they differ from the server side (e.g. Reverb behind a proxy). |

```php
// config/craft/walkie.php
return [
    'connection' => env('WALKIE_CONNECTION', 'reverb'),
    'queueBroadcasts' => true,
    'queue' => 'broadcasts',
    'clientHost' => env('VITE_REVERB_HOST'),
    'clientPort' => (int) env('VITE_REVERB_PORT', 443),
];
```

## What the browser gets

`walkie.config()` and GraphQL's `walkieChannels.echo` hand the browser the broadcaster, the **public** app key,
host, port and TLS flag. Never the secret or the app id. Drivers browsers can't connect to (`log`, `null`,
`redis`, and `ably`, whose key is a secret) give `null`: configure Echo yourself for those.
