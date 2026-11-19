<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use JustinHoltWeb\Walkie\Gql\ChannelsQuery;

use function CraftCms\Cms\t;

/** One GraphQL schema permission: “View Walkie’s public channels”. */
class RegisterGqlComponents
{
    public function handle(GqlSchemaComponentsResolving $event): void
    {
        $event->queries['Walkie'] = [
            ChannelsQuery::COMPONENT.':read' => [
                'label' => t('View Walkie’s public channels', category: 'walkie'),
                'info' => t('Adds the `walkieChannels` query: public channel names for sections this schema can read, and the public WebSocket connection details.', category: 'walkie'),
                'authenticated' => true,
            ],
        ];
    }
}
