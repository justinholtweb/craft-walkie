---
title: Presence
slug: presence
order: 60
summary: Who else is looking at an entry or asset, with nothing but a name.
---

> Walkie is for **Craft 6**, which is still in **beta** (so is Walkie, `6.0.0-beta.1`). Use caution on production sites.

Switch on **Presence** in settings. Then `presence-walkie.{site}.entry.{id}` (and `…asset.{id}`) can be
joined by anyone allowed on the matching private channel. Each member is `{id, name}`: the user id and their
friendly name (first name, else full name, else username). Never an email address.

```js
walkie.here({ id: 12 }, {
  here: (people) => render(people),
  joining: (person) => add(person),
  leaving: (person) => remove(person),
});
```

Twig: `walkie.presenceChannel(entry)` (null while presence is off). Guests can't join presence channels, and
there are none for section or volume feeds.

Walkie doesn't yet draw a "who's editing" indicator in the control panel itself: that needs Echo loaded in the
CP, which Craft 6 doesn't do. The channels are ready for it.
