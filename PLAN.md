# Walkie — plan

Real-time Craft: element saves, deletes and status changes broadcast over Reverb or Pusher, with channels that respect Craft permissions.

- Package: `justinholtweb/craft-walkie` · handle `walkie` · namespace `JustinHoltWeb\Walkie`
- Editions: single edition · pricing: Free (provisional)
- Laravel integration: Laravel broadcasting (Reverb, Pusher, Ably) — Uses the app's configured broadcaster; laravel/reverb is suggested, not required.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Why it exists
Craft 6 core only tells *the same user's other CP tabs* about saves (`BroadcastElementChanges` piggybacks on the
response). Nothing pushes changes across users or to the front end. Walkie does, through Laravel broadcasting,
and becomes the shared real-time layer for Live, Pigeon, Queuebert and Alarm Clock when they're ported.

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
