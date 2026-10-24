<?php

namespace App\Livewire\Admin\Concerns;

use App\Support\Admin\WorkQueues;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * ?antrean=<key> on a list page: the dashboard's queue rows link here, and
 * the list applies the very same WorkQueues query so it matches the count.
 * An unknown key, or another page's key, leaves the list unfiltered.
 */
trait FiltersByWorkQueue
{
    #[Url(as: 'antrean')]
    public string $queue = '';

    public function clearQueue(): void
    {
        $this->queue = '';
        $this->resetPage();
    }

    protected function applyQueue(Builder $query, string $page): Builder
    {
        $ids = $this->queue !== '' ? WorkQueues::filter($page, $this->queue) : null;

        return $ids ? $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids) : $query;
    }

    protected function queueLabel(string $page): ?string
    {
        return $this->queue !== '' && WorkQueues::filter($page, $this->queue) ? WorkQueues::label($this->queue) : null;
    }
}
