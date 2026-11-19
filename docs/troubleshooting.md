---
title: Troubleshooting
slug: troubleshooting
order: 90
summary: Nothing arrives, 403s and 419s from /broadcasting/auth, and the Craft 6 beta.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

**Nothing arrives.** `php artisan craft:walkie:status`: is the connection `log` or `null`? Those don't send
anything. Is delivery queued with no worker running? Is the Reverb server up (`php artisan reverb:start`)?
Failed broadcasts are logged (`Walkie couldn't broadcast…`, at most once a minute) and never fail the save.

**Drafts don't broadcast.** By design, like core. Publishing a draft does (as a save of the entry).

**403 from /broadcasting/auth.** The user isn't allowed on that channel (see [Channels](channels.md)), isn't
signed in, the channel names an unknown site/section/id, or presence is off.

**419 from /broadcasting/auth.** CSRF. `walkie.config()` includes `csrfToken` for signed-in visitors and the
client sends it; a statically cached page has a stale token, so render the config uncached.

**My app has its own /broadcasting/auth.** It wins, and Walkie registers its channels on the default
broadcaster when that route runs. If Walkie uses a different connection than your default, point Echo's
`authEndpoint` at a route that uses Walkie's connection, or make Walkie's connection the default.

**Guests see nothing on a public channel.** The section must be ticked under Public sections, the site enabled,
and the entry live. Pending entries are never announced.

**Craft 6 beta.** Walkie tracks Craft 6 betas; after a Craft update, `php artisan craft:up` and clear caches.
Report anything odd on GitHub.
