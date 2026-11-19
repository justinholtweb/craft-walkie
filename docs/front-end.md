---
title: Front end (Twig, Blade, JS)
slug: front-end
order: 40
summary: The walkie template global, the Echo config snippet and the JS client.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

## Template global

The same object in both engines: `walkie` in Twig, `$walkie` in Blade. Names come back **without** Echo's
`private-`/`presence-` prefixes, ready for `Echo.channel()`, `Echo.private()` or `Echo.join()`. Anything
unknown returns null.

| Twig | Blade | Returns |
|---|---|---|
| `walkie.channel(entry)` | `$walkie->channel($entry)` | the public channel, or null unless the entry's section is public |
| `walkie.privateChannel(entry)` | `$walkie->privateChannel($entry)` | the private channel (entries and assets) |
| `walkie.presenceChannel(entry)` | `$walkie->presenceChannel($entry)` | the presence channel, when presence is on |
| `walkie.sectionChannel('news', site, private)` | `$walkie->sectionChannel('news', $site, $private)` | a section feed (public only for public sections) |
| `walkie.volumeChannel('images', site)` | `$walkie->volumeChannel('images', $site)` | a volume feed (private) |
| `walkie.config(site)` | `$walkie->config($site)` | the JS client config (array) |
| `walkie.script(site)` | `{{ $walkie->script($site) }}` | `<script>` tags that start the client |

`site` is a site handle and defaults to the current site; an unknown handle returns null.

`config()` holds the site, the auth endpoint, Echo options for the connection (public key and host only), and
`csrfToken`, but only for signed-in visitors, who already have a session: guest pages stay cacheable.

## The JS client

`script()` loads `/vendor/justinholtweb/craft-walkie/walkie.js` and starts it as `window.walkie` once
`window.Echo` exists (an Echo instance, or the Echo class with `window.Pusher`), then fires `walkie:ready`.
Or import it in your bundle:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { createWalkie } from '/vendor/justinholtweb/craft-walkie/walkie.js';

window.Pusher = Pusher;
const walkie = createWalkie(Echo, window.walkieConfig); // or createWalkie(new Echo({...}), config)
```

```js
const off = walkie.on({ id: 12 }, 'saved', ({ id }) => refetch(id));   // public entry channel
walkie.on({ id: 12, private: true }, '*', (msg) => console.log(msg));  // every private event
walkie.on({ id: 12, private: true }, 'status', (msg) => badge(msg.status)); // status changes, however they happened
walkie.onSection('news', 'saved', ({ id }) => prepend(id));             // public feed
walkie.onSection('news', 'deleted', ({ id }) => drop(id), { private: true });
walkie.onVolume('images', 'saved', ({ id }) => refreshThumb(id));       // private
off();                                                                   // stop listening (leaves the channel when unused)
```

Public events are `saved` and `removed`; private ones `created`, `saved`, `restored`, `status` and `deleted`.
`status` in the client also fires for saves that changed the status.

## Refetching

Messages carry ids only. Typical patterns: reload a fragment (`fetch(location.href)` and swap a container),
call your element API or GraphQL for the id, or re-render a Sprig/htmx component.
