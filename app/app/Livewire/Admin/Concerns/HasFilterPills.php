<?php

namespace App\Livewire\Admin\Concerns;

use Livewire\Attributes\Url;

/** ?filter=<key> for list pages whose pills aren't a status column of their own. */
trait HasFilterPills
{
    #[Url(as: 'filter')]
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }
}
