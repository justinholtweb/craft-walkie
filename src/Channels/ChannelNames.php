<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Channels;

/**
 * Walkie's channel names. Each is per site, so a subscriber only hears about one site.
 *
 *   walkie.{site}.entry.{id}          one entry          public*, private-, presence-
 *   walkie.{site}.entries.{section}   a section's feed   public*, private-
 *   walkie.{site}.asset.{id}          one asset          private-, presence-
 *   walkie.{site}.assets.{volume}     a volume's feed    private-
 *
 * * Public (unprefixed) channels only carry live entries of sections switched on in
 *   “Public sections”. The same name with `private-` carries everything a viewer with
 *   the matching Craft permissions may see. Names never contain anything but handles and ids.
 */
final class ChannelNames
{
    public const string PREFIX = 'walkie';

    /** Laravel channel patterns, as registered with the broadcaster. */
    public const array PATTERNS = [
        'entry' => 'walkie.{site}.entry.{id}',
        'entries' => 'walkie.{site}.entries.{section}',
        'asset' => 'walkie.{site}.asset.{id}',
        'assets' => 'walkie.{site}.assets.{volume}',
    ];

    /** Element types Walkie broadcasts, by the type segment of their channel name. */
    public const array FEEDS = [
        'entry' => 'entries',
        'asset' => 'assets',
    ];

    public static function element(string $site, string $type, int $id): string
    {
        return self::PREFIX.".$site.$type.$id";
    }

    public static function feed(string $site, string $type, string $group): string
    {
        return self::PREFIX.".$site.".self::FEEDS[$type].".$group";
    }

    public static function isWalkie(string $channel): bool
    {
        return (bool) preg_match('/^(private-|presence-)?'.self::PREFIX.'\./', $channel);
    }
}
