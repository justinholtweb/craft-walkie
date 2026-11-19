---
title: Channels and permissions
slug: channels
order: 30
summary: Channel names, who may subscribe, what each carries, and what never leaves the server.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

Every channel is **per site**: a subscriber hears about one site only. Site, section and volume handles are
Craft handles (no dots), so a channel name always parses one way.

## Private channels

| Channel | Subscribe if… |
|---|---|
| `private-walkie.{site}.entry.{id}` | Craft's entry policy lets the user **view that entry in that site**: `viewEntries`, plus `viewPeerEntries` for other people's entries, plus `editSite` on multi-site installs. Disabled and pending entries count (CP viewers can see them). |
| `private-walkie.{site}.entries.{section}` | `viewEntries` **and** `viewPeerEntries` for the section (a feed shows everyone's entries; singles only need `viewEntries`), the section is enabled for the site, plus `editSite`. |
| `private-walkie.{site}.asset.{id}` | Craft's asset policy: `viewAssets`, `viewPeerAssets` for others' uploads, `editSite`. |
| `private-walkie.{site}.assets.{volume}` | `viewAssets` and `viewPeerAssets`, plus `editSite`. |

Refused: guests; unknown sites, sections, volumes; malformed ids (`1e3`, `012`); ids of drafts, revisions
or trashed elements; anything not a Walkie channel (unless your app registered it). Subscriptions are checked
when they're made: revoking a permission takes effect on the next subscribe, as with any Pusher-protocol server.

## Public channels

`walkie.{site}.entry.{id}` and `walkie.{site}.entries.{section}` need no auth, so Walkie decides what goes on
them: only entries in **public sections**, on **enabled sites**, and only:

- `saved` while the entry is live (including the moment it's published, created live, or restored live);
- `removed` when it stops being live (disabled, expired, deleted).

Pending (future) and disabled entries are never mentioned on a public channel, not even by id. Assets have no
public channels.

## Payloads

Private:

```json
{"event": "saved", "id": 12, "type": "entry", "site": "default", "section": "news",
 "status": "disabled", "previousStatus": "live", "causedBy": 1, "dateUpdated": "2026-11-18T10:00:00+00:00"}
```

`causedBy` (the editor's user id) is present when the change came from a signed-in request. Deletes have no
`status`; creates no `previousStatus`. Assets carry `volume` instead of `section`.

Public:

```json
{"event": "saved", "id": 12, "type": "entry", "site": "default", "section": "news"}
```

No titles, field values, URLs or authors, ever: fetch what you show through your own templates, element API
or GraphQL, which apply their own permissions.

## When broadcasts go out

- After the database transaction commits. A rolled-back save sends nothing.
- Once per element per site per request: two saves of the same entry, or an entry and its nested entries,
  fold into one message (the first `previousStatus`, the last `status`, the strongest event).
- Per site: a save that propagates to three sites sends a message on each site's channels, with that site's status.
- A nested entry (Matrix) saved on its own announces its owner as `saved`.
