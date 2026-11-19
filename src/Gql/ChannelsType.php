<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Gql;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ChannelsType
{
    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('WalkieChannels', fn () => new ObjectType([
            'name' => 'WalkieChannels',
            'description' => 'Walkie’s public channels for one site.',
            'fields' => fn () => [
                'site' => ['type' => Type::nonNull(Type::string()), 'description' => 'The site handle the channels belong to.'],
                'sections' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(self::sectionType()))),
                    'description' => 'Public section feeds this schema can read. Each carries `saved` and `removed` messages with entry ids.',
                ],
                'entryChannel' => [
                    'type' => Type::string(),
                    'args' => ['id' => ['name' => 'id', 'type' => Type::nonNull(Type::int())]],
                    'resolve' => [ChannelsResolver::class, 'entryChannel'],
                    'description' => 'The public channel of a live entry in a public section this schema can read; null otherwise.',
                ],
                'echo' => [
                    'type' => self::echoType(),
                    'description' => 'Laravel Echo options for the broadcast connection (public key and host only); null when browsers can’t connect to it.',
                ],
            ],
        ]));
    }

    private static function sectionType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('WalkieSectionChannel', fn () => new ObjectType([
            'name' => 'WalkieSectionChannel',
            'fields' => [
                'section' => ['type' => Type::nonNull(Type::string()), 'description' => 'The section handle.'],
                'channel' => ['type' => Type::nonNull(Type::string()), 'description' => 'The public channel name (Echo.channel()).'],
            ],
        ]));
    }

    private static function echoType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate('WalkieEcho', fn () => new ObjectType([
            'name' => 'WalkieEcho',
            'fields' => [
                'broadcaster' => ['type' => Type::nonNull(Type::string())],
                'key' => ['type' => Type::nonNull(Type::string()), 'description' => 'The public app key.'],
                'cluster' => ['type' => Type::string()],
                'wsHost' => ['type' => Type::string()],
                'wsPort' => ['type' => Type::int()],
                'wssPort' => ['type' => Type::int()],
                'forceTLS' => ['type' => Type::nonNull(Type::boolean())],
            ],
        ]));
    }
}
