<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Listeners;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Contracts\NestedElementInterface;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementDeletedForSite;
use CraftCms\Cms\Element\Events\ElementDeleting;
use CraftCms\Cms\Element\Events\ElementRestored;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\Element\Listeners\BroadcastElementChanges;
use CraftCms\Cms\Entry\Elements\Entry;
use JustinHoltWeb\Walkie\Changes\Change;
use JustinHoltWeb\Walkie\Changes\Outbox;
use JustinHoltWeb\Walkie\Settings;
use JustinHoltWeb\Walkie\Walkie;

/**
 * Element events → {@see Change}s in the {@see Outbox}.
 *
 * Mirrors core's {@see BroadcastElementChanges} guards (no drafts,
 * revisions or bulk resaves) but keeps propagated saves: each site gets its own message, with
 * that site's status. Core's same-user CP-tab messages are left alone; this is the
 * cross-user and front-end layer on top.
 *
 * Scoped: one per request, job or command.
 */
class CaptureElementChanges
{
    /** @var array<int, array<int, string|null>> element id → site id → status, captured before a delete */
    private array $beforeDelete = [];

    /** @var array<int, int> ids of elements being saved right now (to fold nested saves into them) */
    private array $saving = [];

    public function __construct(
        private readonly Walkie $walkie,
        private readonly Outbox $outbox,
        private readonly Settings $settings,
    ) {}

    public function handleSaving(ElementSaving $event): void
    {
        if ($event->element->id) {
            $this->saving[(int) $event->element->id] = ($this->saving[(int) $event->element->id] ?? 0) + 1;
        }
    }

    public function handleSaved(ElementSaved $event): void
    {
        $element = $event->element;

        if ($element->id && isset($this->saving[(int) $element->id]) && --$this->saving[(int) $element->id] <= 0) {
            unset($this->saving[(int) $element->id]);
        }

        if (! $this->settings->broadcastSaves || $this->skip($element)) {
            return;
        }

        if ($this->walkie->typeOf($element) === null) {
            $this->touchOwner($element);

            return;
        }

        $previous = $element instanceof Entry ? $element->oldStatus : $element->getStatus();
        $change = $this->walkie->change($element, $event->isNew ? Change::CREATED : Change::SAVED, $event->isNew ? null : $previous);

        if ($change !== null) {
            $this->outbox->push($change);
        }
    }

    public function handleDeleting(ElementDeleting $event): void
    {
        $element = $event->element;

        if (! $this->settings->broadcastDeletes || $this->skip($element) || $this->walkie->typeOf($element) === null) {
            return;
        }

        // Deleting removes the element from every site: remember each site's status first.
        $this->beforeDelete[(int) $element->id] = collect($element::find()
            ->id($element->id)
            ->site('*')
            ->status(null)
            ->all())
            ->mapWithKeys(fn (ElementInterface $e) => [(int) $e->siteId => $e->getStatus()])
            ->all();
    }

    public function handleDeleted(ElementDeleted $event): void
    {
        $element = $event->element;

        if (! $this->settings->broadcastDeletes || $this->skip($element)) {
            return;
        }

        $statuses = $this->beforeDelete[(int) $element->id] ?? [(int) $element->siteId => $element->getStatus()];
        unset($this->beforeDelete[(int) $element->id]);

        foreach ($statuses as $siteId => $status) {
            $copy = clone $element;
            $copy->siteId = $siteId;

            if ($change = $this->walkie->change($copy, Change::DELETED, $status)) {
                $this->outbox->push($change);
            }
        }
    }

    public function handleDeletedForSite(ElementDeletedForSite $event): void
    {
        $element = $event->element;

        if ($this->settings->broadcastDeletes && ! $this->skip($element)
            && $change = $this->walkie->change($element, Change::DELETED, $element->getStatus())) {
            $this->outbox->push($change);
        }
    }

    public function handleRestored(ElementRestored $event): void
    {
        $element = $event->element;

        if (! $this->settings->broadcastDeletes || $this->skip($element) || $this->walkie->typeOf($element) === null) {
            return;
        }

        foreach ($element::find()->id($element->id)->site('*')->status(null)->all() as $restored) {
            if ($change = $this->walkie->change($restored, Change::RESTORED, null)) {
                $this->outbox->push($change);
            }
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            ElementSaving::class => 'handleSaving',
            ElementSaved::class => 'handleSaved',
            ElementDeleting::class => 'handleDeleting',
            ElementDeleted::class => 'handleDeleted',
            ElementDeletedForSite::class => 'handleDeletedForSite',
            ElementRestored::class => 'handleRestored',
        ];
    }

    private function skip(ElementInterface $element): bool
    {
        return ! $element->id
            || $element->getIsDraft()
            || $element->getIsRevision()
            || $element->resaving
            || $element->duplicateOf !== null;
    }

    /**
     * A nested entry (Matrix block) saved on its own changes its owner's content, which
     * subscribers to the owner want to hear. Saved as part of its owner, the owner's own
     * message covers it.
     */
    private function touchOwner(ElementInterface $element): void
    {
        if ($element->propagating || ! $element instanceof NestedElementInterface) {
            return;
        }

        $owner = $element->getPrimaryOwner();

        if ($owner === null || isset($this->saving[(int) $owner->id]) || $this->skip($owner) || $this->walkie->typeOf($owner) === null) {
            return;
        }

        if ($change = $this->walkie->change($owner, Change::SAVED, $owner->getStatus())) {
            $this->outbox->push($change);
        }
    }
}
