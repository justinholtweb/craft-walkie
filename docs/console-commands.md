---
title: Console commands
slug: console-commands
order: 80
summary: craft:walkie:scheduled and craft:walkie:status.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

```bash
php artisan craft:walkie:scheduled [--since="-10 minutes"]
```

Broadcasts `status` for entries whose post date or expiry date passed since the last run (pending → live,
live → expired), plus the public `saved`/`removed`. Scheduled every minute; the window is remembered, and
`--since` can reach back at most a day. Does nothing when status-change broadcasts are off.

```bash
php artisan craft:walkie:status
```

The connection and driver, queued or immediate delivery, what browsers get, who owns `/broadcasting/auth`
(Walkie, the app, or nobody), which events are on, presence, and the public sections. Prints no secrets.
