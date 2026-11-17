# Walkie

Real-time Craft: element saves, deletes and status changes broadcast over Reverb or Pusher, with channels that respect Craft permissions.

> **Status:** scaffold — nothing user-facing yet. See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Laravel integration: Laravel broadcasting (Reverb, Pusher, Ably). Uses the app's configured broadcaster; laravel/reverb is suggested, not required.

## Editions and pricing

single edition — Free (provisional).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-walkie --install`).
