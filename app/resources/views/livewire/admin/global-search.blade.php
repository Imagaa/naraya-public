<div x-data
     @keydown.window.ctrl.k.prevent="$refs.dialog.showModal()"
     @keydown.window.meta.k.prevent="$refs.dialog.showModal()"
     @open-search.window="$refs.dialog.showModal()">
    <dialog x-ref="dialog" class="adm-search" wire:ignore.self @click.self="$refs.dialog.close()" aria-label="Pencarian">
        <form wire:submit="go" class="adm-search-form">
            <x-lucide-search width="18" height="18" aria-hidden="true" />
            <input type="search" wire:model.live.debounce.250ms="term" placeholder="Cari order, toko, produk, sales…" aria-label="Cari" autocomplete="off" autofocus>
            <kbd>Esc</kbd>
        </form>
        <div class="adm-search-results">
            @if(mb_strlen(trim($term)) < 2)
                <p class="adm-search-empty">Ketik minimal 2 huruf. Enter membuka hasil pertama.</p>
            @else
                @forelse($groups as $group => $hits)
                    <section>
                        <h3>{{ $group }}</h3>
                        @foreach($hits as $hit)
                            <a href="{{ $hit['url'] }}">{{ $hit['label'] }} <small>{{ $hit['meta'] }}</small></a>
                        @endforeach
                    </section>
                @empty
                    <p class="adm-search-empty">Tidak ada hasil untuk "{{ $term }}".</p>
                @endforelse
            @endif
        </div>
    </dialog>
</div>
