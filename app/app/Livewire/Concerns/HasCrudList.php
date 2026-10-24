<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait HasCrudList
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'components.admin.pagination';
    }
}
