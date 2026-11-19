# Walkie — plan

Real-time Craft: element saves, deletes and status changes broadcast over Reverb or Pusher, with channels that respect Craft permissions.

- Package: `justinholtweb/craft-walkie` · handle `walkie` · namespace `JustinHoltWeb\Walkie`
- Editions: single edition · pricing: Free (provisional, see questions)
- Laravel integration: Laravel broadcasting (Reverb, Pusher, Ably) — Uses the app's configured broadcaster; laravel/reverb is suggested, not required.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Why it exists
Craft 6 core only tells *the same user's other CP tabs* about saves (`BroadcastElementChanges` piggybacks on the
response). Nothing pushes changes across users or to the front end. Walkie does, through Laravel broadcasting,
and becomes the shared real-time layer for Live, Pigeon, Queuebert and Alarm Clock when they're ported.

## Status (2026-11-18)

**6.0.0-beta.1 feature-complete for Phases 1–5**, not yet released. Free (Craft License).

- Phase 1 ✅ entries + assets: created/saved/restored/deleted + scheduled `status`; per site; after commit;
  folded per element/site; nested entries announce their owner. Users, Commerce products and other plugins'
  elements are deliberately not broadcast yet (see questions).
- Phase 2 ✅ private channels via Craft's element policies + section/volume permissions + `editSite`; public
  channels per section (live entries only, ids only). `/broadcasting/auth` registered when the app has none
  (answers the risk below: stock Craft 6 has neither `config/broadcasting.php` routes nor `routes/channels.php`).
- Phase 3 ✅ `walkie` global (Twig + Blade parity), `walkie.js` ESM client over Echo, `script()`.
- Phase 4 ✅ presence channels (`{id, name}` only). ⏳ No CP "who's editing" UI yet (needs Echo in the CP).
- Phase 5 ✅ `Walkie::send()`, `WalkieBroadcast` event, docs/events.md.
- GraphQL ✅ `walkieChannels` (public channels only). Multisite ✅. Translations ✅ en + fr. Icon ✅.
- Tests: 39 Pest, 7 node (client vs fake Echo), harness `tests/Harness/check.sh` (single site and
  `WALKIE_MULTISITE=1`) against Laravel's real Pusher broadcaster with broadcasts captured to a file.
- **Unverified:** delivery through a real Reverb/Pusher server and a real browser Echo connection (no
  server in the harness); Ably (Echo config deliberately not generated: its key is a secret).

## Phases
1. **Element events → broadcasts.** `ElementSaved`/`ElementDeleted`/status transitions become `ShouldBroadcast`
   events on `walkie.{elementType}.{id}`, `walkie.section.{handle}`, `walkie.site.{handle}`. Payload is ids +
   minimal metadata only (never field content — clients refetch).
2. **Channel authorisation.** `routes/channels.php`-style callbacks mapped to Craft permissions (`viewEntries:{uid}`
   etc.), guest-visible public channels for live front ends only when explicitly enabled per section.
3. **Client.** Tiny ESM client (wraps Laravel Echo) + Twig `{{ walkie.client() }}`; `walkie.on(entry, 'saved', cb)`.
4. **Presence.** Presence channels per element: "who's viewing/editing" for front-end and CP use.
5. **Family adoption.** Document the event contract so Live/Pigeon/Queuebert can depend on it.

## Risks / open questions
- Is broadcasting configured in a stock Craft 6 app (no `config/broadcasting.php`, no `routes/channels.php`)?
  Walkie may need to register the broadcast routes itself.
- Don't broadcast during resaves/propagation (mirror core's guards).

## Definition of done (every phase)
`bin/port-check craft-walkie` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.

## Questions for Justin

1. **Reverb in the harness?** A Reverb DDEV add-on (or `laravel/reverb` + a `reverb:start` daemon in the web
   container) would let check.sh prove real WebSocket delivery and a browser Echo connection. Want me to set
   that up, or will you add an add-on? (I didn't install anything into DDEV.)
2. **Harness changes I made (under the lock):** `composer require pusher/pusher-php-server`, added
   `app/Broadcasting/WalkieCaptureBroadcaster.php` and a `walkie-capture` connection in `AppServiceProvider`.
   OK to keep? (Walkie is left installed with broadcasting switched off so others' saves don't queue jobs.)
3. **CP presence UI:** a "someone else is editing" badge in the entry editor needs Laravel Echo + pusher-js
   loaded on every CP page (~60 KB) when a browser-reachable connection is configured. Build it (opt-in setting)?
4. **More element types?** Users are left out on privacy grounds; Commerce products and other plugins'
   elements could follow the same pattern (element policy for private channels). Which, if any, for 6.0?
5. **Free confirmed?** PLAN said "Free (provisional)". Everything is built as a single free edition.
6. **CSRF on /broadcasting/auth:** Walkie keeps CSRF on (Laravel's own route turns it off). Statically cached
   pages then need the config rendered uncached for signed-in users. Keep it on? (I'd keep it.)
7. **GraphQL is deliberately thin** (public channel names + public connection details): a token isn't a
   browser session, so private channels stay with `/broadcasting/auth`. Enough?
