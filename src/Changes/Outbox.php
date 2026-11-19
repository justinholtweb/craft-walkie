<?php

declare(strict_types=1);

namespace JustinHoltWeb\Walkie\Changes;

use Illuminate\Support\Facades\DB;
use JustinHoltWeb\Walkie\Walkie;

/**
 * Holds changes until the database transaction they happened in commits, then broadcasts
 * each element/site once. A save that propagates to five sites, or touches an entry and its
 * nested entries, sends one message per site rather than a burst, and a rolled-back save
 * sends nothing (clients would refetch the old content).
 *
 * Scoped: one per request, job or command.
 */
class Outbox
{
    /** @var array<string, Change> */
    private array $pending = [];

    public function __construct(private readonly Walkie $walkie) {}

    public function push(Change $change): void
    {
        $key = $change->key();
        $this->pending[$key] = isset($this->pending[$key]) ? $this->pending[$key]->merge($change) : $change;

        // Runs now when no transaction is open, else after the outermost commit (and never on rollback).
        DB::afterCommit(fn () => $this->flush($key));
    }

    public function flush(string $key): void
    {
        if (! isset($this->pending[$key])) {
            return;
        }

        $change = $this->pending[$key];
        unset($this->pending[$key]);
        $this->walkie->dispatch($change);
    }

    /** @return array<string, Change> */
    public function pending(): array
    {
        return $this->pending;
    }
}
