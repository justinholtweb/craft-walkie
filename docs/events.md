---
title: Events for other plugins
slug: events
order: 50
summary: The broadcast contract, the WalkieBroadcast PHP event, and Walkie::send() for your own events.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

Walkie is meant to be the shared real-time layer for other plugins (live blogs, messaging, queue monitors,
scheduled content). The contract below is stable within 6.x.

## Send your own event

```php
use JustinHoltWeb\Walkie\Walkie;

// On the element's private channel and its section/volume feed: only people who may view it hear it.
app(Walkie::class)->send($entry, 'liveUpdate', ['updateId' => $update->id]);

// Also on the public channels, but only while the entry is live in a public section.
app(Walkie::class)->send($entry, 'liveUpdate', ['updateId' => $update->id], public: true);
```

- Event names: `[a-z][A-Za-z0-9_.-]{0,63}`, not one of Walkie's own (`created`, `saved`, `restored`, `status`,
  `deleted`, `removed`). Anything else throws `InvalidArgumentException`.
- `id`, `type` and `site` are always set by Walkie and can't be overridden. Payloads are limited to 8 KB of JSON.
- Drafts and revisions are never sent (returns false). It goes through the same connection, queue and failure
  handling as Walkie's own broadcasts.
- **The payload is yours to keep safe.** Send ids and small facts, never content a subscriber might not be
  allowed to see; subscribers fetch the rest.

Clients listen with `walkie.on({ id }, 'liveUpdate', cb)` (or Echo's `.listen('.liveUpdate', cb)`).

## Listen in PHP

Every broadcast is also a Laravel event:

```php
use JustinHoltWeb\Walkie\Broadcasting\WalkieBroadcast;

Event::listen(WalkieBroadcast::class, function (WalkieBroadcast $b) {
    // $b->name ('saved', 'removed', 'liveUpdate'…), $b->channels, $b->payload
});
```

## Channel helpers

`JustinHoltWeb\Walkie\Channels\ChannelNames::element($site, 'entry', $id)` and `::feed($site, 'entry', $section)`
build names; `app(\JustinHoltWeb\Walkie\Template\WalkieApi::class)` is the template global, for PHP code.
