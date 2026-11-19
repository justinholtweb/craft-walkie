---
title: FAQ
slug: faq
order: 100
summary: Craft 6 and the beta, price, servers, and what Walkie never sends.
---

## Does Walkie work on Craft 5?

No. It's a native **Craft 6** plugin built on Laravel broadcasting. Craft 6 is still in beta.

## Is it safe to use in production?

Use caution. Craft 6 and Walkie (`6.0.0-beta.1`) are both in beta, so expect changes between releases. Test on
staging, keep backups, and move to production once you're comfortable.

## What does it cost?

Walkie is free, under the Craft License.

## Do I need Reverb?

You need something browsers can connect to: Reverb (free, self-hosted), Pusher, or a Pusher-protocol server.
Walkie itself only uses Laravel's broadcasting API.

## How is this different from what Craft 6 already does?

Core tells **your own** other control panel tabs about your saves, through the response. Walkie tells **everyone
allowed to know**, other editors and front ends, through a WebSocket server, per site, with permission checks.
Walkie builds on core's guards and leaves its messages alone.

## Will it leak unpublished content?

Public channels only ever mention live entries of sections you made public. Private channels need Craft's view
permissions for that element or section in that site. Payloads never contain content, titles or URLs.

## Can other plugins use it?

Yes: `Walkie::send()` broadcasts your own events on an element's channels. See [Events](events.md).
