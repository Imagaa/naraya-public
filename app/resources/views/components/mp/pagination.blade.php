{{-- Livewire pager for buyer pages. Livewire's default view is styled with
     Tailwind utilities that marketplace.css doesn't ship, so its arrows
     rendered at full width. Used via $paginator->links('components.mp.pagination'). --}}
<div>
    @if($paginator->hasPages())
        <nav class="pager" aria-label="Halaman">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="document.body.scrollIntoView()" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) aria-label="Halaman sebelumnya"><x-lucide-chevron-left class="ico ico-sm" />Sebelumnya</button>
            <span class="num" title="Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}">{{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}</span>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="document.body.scrollIntoView()" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) aria-label="Halaman berikutnya">Berikutnya<x-lucide-chevron-right class="ico ico-sm" /></button>
        </nav>
    @endif
</div>
