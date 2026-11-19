---
title: GraphQL
slug: graphql
order: 70
summary: walkieChannels — public channel names and connection details for headless front ends.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

Tick **View Walkie's public channels** on a schema to add `walkieChannels`:

```graphql
{
  walkieChannels(site: "default") {
    site
    sections { section channel }      # public sections this schema can read, enabled on the site
    entryChannel(id: 12)              # a live entry in a public section the schema can read, else null
    echo { broadcaster key wsHost wsPort wssPort forceTLS cluster }
  }
}
```

- Public channels only. A GraphQL token isn't a browser session, so private channels stay with `/broadcasting/auth`.
- Sections are filtered by the schema's own section permissions; `entryChannel` goes through Craft's entries
  resolver, so an entry the schema couldn't query returns null, as does a disabled, pending or expired one.
- `echo` is the public half of the connection (never the secret or app id), null for drivers browsers can't use.
- A site the schema can't read is an error, not an empty result.
