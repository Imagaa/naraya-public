<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="admin-pagination">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary admin-pagination-disabled" aria-disabled="true">‹</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary" aria-label="{{ __('pagination.previous') }}">‹</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="admin-pagination-ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary" aria-label="{{ __('pagination.next') }}">›</button>
            @else
                <span class="btn btn-secondary admin-pagination-disabled" aria-disabled="true">›</span>
            @endif
        </nav>
    @endif
</div>
