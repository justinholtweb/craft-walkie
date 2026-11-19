<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Gql\GqlQueries;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use JustinHoltWeb\Walkie\Changes\Outbox;
use JustinHoltWeb\Walkie\Channels\ChannelAuthorizer;
use JustinHoltWeb\Walkie\Template\WalkieApi;
use Override;

use function CraftCms\Cms\t;

/**
 * Walkie — real-time Craft: element saves, deletes and status changes broadcast over
 * Reverb or Pusher, with channels that respect Craft permissions.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    protected array $events = [
        TemplateGlobalsResolving::class => Listeners\RegisterTemplateApi::class,
        RouteMatched::class => Listeners\RegisterChannelsForAppRoute::class,
        GqlSchemaComponentsResolving::class => Listeners\RegisterGqlComponents::class,
    ];

    protected array $commands = [
        Commands\ScheduledCommand::class,
        Commands\StatusCommand::class,
    ];

    /** @var array<string, string> */
    protected array $publishables = [
        __DIR__.'/../resources/js/walkie.js' => 'walkie.js',
    ];

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());
        $this->app->scoped(Walkie::class);
        $this->app->scoped(WalkieApi::class);
        $this->app->scoped(ChannelAuthorizer::class);
        // One outbox and one listener per request/job, so a save's sites and nested entries fold together.
        $this->app->scoped(Outbox::class);
        $this->app->scoped(Listeners\CaptureElementChanges::class);
        Event::subscribe(Listeners\CaptureElementChanges::class);
        $this->app->make(GqlQueries::class)->register(Gql\ChannelsQuery::class);
    }

    #[Override]
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('craft:walkie:scheduled')->everyMinute()->withoutOverlapping();
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $sections = Sections::getAllSections()
            ->map(fn ($section) => ['label' => $section->name, 'value' => $section->uid])
            ->values()
            ->all();

        return Ui::make([
            Heading::make('walkie-events', t('What to broadcast', category: 'walkie')),
            Field::make(t('Saves', category: 'walkie'), Lightswitch::make('broadcastSaves'))
                ->instructions(t('When an entry or asset is created or saved (drafts and revisions never are).', category: 'walkie')),
            Field::make(t('Deletes', category: 'walkie'), Lightswitch::make('broadcastDeletes'))
                ->instructions(t('When an entry or asset is deleted or restored.', category: 'walkie')),
            Field::make(t('Scheduled status changes', category: 'walkie'), Lightswitch::make('broadcastStatusChanges'))
                ->instructions(t('When an entry goes live or expires because its post or expiry date passed. Needs Laravel’s scheduler running.', category: 'walkie')),
            Field::make(t('Presence', category: 'walkie'), Lightswitch::make('presence'))
                ->instructions(t('Lets people who can view an element see who else is viewing it (their name only).', category: 'walkie')),
            Field::make(t('Public sections', category: 'walkie'), Choice::make('publicSections')->multiple()->presentation(ChoicePresentation::Checkboxes)->options($sections))
                ->instructions(t('Live entries in these sections are also announced on public channels anyone can subscribe to (ids only). Everything else needs a signed-in user with permission to view it.', category: 'walkie')),
            Heading::make('walkie-delivery', t('Delivery', category: 'walkie')),
            Field::make(t('Broadcast connection', category: 'walkie'), Text::make('connection')->placeholder((string) config('broadcasting.default')))
                ->instructions(t('A connection from config/broadcasting.php (reverb, pusher…). Leave blank for the app’s default.', category: 'walkie')),
            Field::make(t('Queue broadcasts', category: 'walkie'), Lightswitch::make('queueBroadcasts'))
                ->instructions(t('Send from the queue, so a slow WebSocket server never slows down saving. Needs a queue worker.', category: 'walkie')),
            Field::make(t('Queue name', category: 'walkie'), Text::make('queue')->placeholder('default'))
                ->instructions(t('Leave blank for the default queue.', category: 'walkie')),
            Field::make(t('Register /broadcasting/auth', category: 'walkie'), Lightswitch::make('registerAuthRoute'))
                ->instructions(t('Laravel Echo’s auth endpoint, for private and presence channels. An app that registers its own keeps it.', category: 'walkie')),
            Field::make(t('Browser host', category: 'walkie'), Text::make('clientHost')->placeholder('ws.example.com'))
                ->instructions(t('The WebSocket host browsers connect to, if it differs from the server-side host.', category: 'walkie')),
            Field::make(t('Browser port', category: 'walkie'), Number::make('clientPort')->min(1)->max(65535))
                ->instructions(t('The WebSocket port browsers connect to, if it differs.', category: 'walkie')),
        ]);
    }
}
