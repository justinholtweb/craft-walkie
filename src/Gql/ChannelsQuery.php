<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Gql;

use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Queries\Query;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type;

/**
 * `walkieChannels` — for headless front ends: which public channels to subscribe to, and
 * how to connect. Public channels only (a GraphQL token is not a session that could pass
 * channel auth), only sections the schema can read, only live entries. Never the
 * broadcaster's secret or app id.
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
class ChannelsQuery extends Query
{
    public const string COMPONENT = 'walkie';

    /** @return array<string, UnnamedFieldDefinitionConfig> */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && ! GqlHelper::canSchema(self::COMPONENT)) {
            return [];
        }

        return [
            'walkieChannels' => [
                'type' => Type::nonNull(ChannelsType::getType()),
                'args' => [
                    'site' => ['name' => 'site', 'type' => Type::string(), 'description' => 'A site handle (default: the primary site the schema can read).'],
                ],
                'resolve' => [ChannelsResolver::class, 'resolve'],
                'description' => 'Walkie’s public real-time channels for a site, and the WebSocket connection to reach them.',
            ],
        ];
    }
}
