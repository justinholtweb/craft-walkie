# Release Notes for Walkie

> Walkie is for **Craft 6**, which is still in beta (so is Walkie). Use caution on production sites.

## 6.0.0-beta.2 - 2026-11-21

### Changed
- The docs FAQ now explains how Walkie relates to Stop Sign and Live.

## 6.0.0-beta.1 - 2026-11-19

### Added
- Entry and asset saves, deletes, restores and scheduled status changes broadcast through Laravel broadcasting (Reverb, Pusher or any Pusher-protocol server), per site.
- Private channels authorised with Craft's own permissions: `walkie.{site}.entry.{id}` and `walkie.{site}.asset.{id}` through Craft's element policies (`viewEntries`/`viewPeerEntries`, `viewAssets`/`viewPeerAssets`, `editSite`), section and volume feeds with view + view-peer permissions.
- Public channels for sections you switch on: only live entries, ids only (`saved` / `removed`). Pending and disabled entries are never mentioned.
- Minimal payloads: ids, site, section/volume and status. Never titles, fields or URLs.
- Broadcasts go out after the database transaction commits (nothing on rollback), once per element per site per request.
- Drafts, revisions, duplicates and bulk resaves are never broadcast, mirroring core's `BroadcastElementChanges`, which keeps telling your own CP tabs.
- `POST /broadcasting/auth` for stock Craft 6 apps (Craft's session, CSRF-protected); an app's own route keeps working and gets Walkie's channels.
- Presence channels (`presence-walkie.{site}.entry.{id}`): who's here, as `{id, name}`.
- `craft:walkie:scheduled` (every minute) for post and expiry dates passing; `craft:walkie:status`.
- Twig `walkie` / Blade `$walkie` global: channel names, Echo config (public key and host only) and `script()`.
- A small ESM client over Laravel Echo: `walkie.on()`, `onSection()`, `onVolume()`, `here()`.
- `Walkie::send()` for other plugins to broadcast their own events on an element's channels, and the `WalkieBroadcast` Laravel event.
- GraphQL: `walkieChannels` (public channels the schema can read, and the public connection details).
- Queued or immediate delivery, a configurable connection and queue; a down or misconfigured broadcaster is logged and never fails a save.
- English and French translations.
