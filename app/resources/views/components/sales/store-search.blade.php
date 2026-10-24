{{--
    Autocomplete over the sales person's own stores (Alpine storeSearch in resources/js/sales.js). `stores`: rows with id, name, owner, sub (address) and days.
    Suggestions float over a blurred page; a row opens the store sheet of the Livewire page around it.
--}}
@props(['stores'])
@php($uid = 'ssearch-'.\Illuminate\Support\Str::random(6)) {{-- two searches on Kerja: today's card and the panel --}}

<div class="ssearch" x-data="storeSearch(@js($stores->map(fn ($s) => \Illuminate\Support\Arr::only($s, ['id', 'name', 'owner', 'sub', 'days']))->values()))"
     :class="{ 'is-open': open && q }" x-on:keydown.escape.window="close()">
    <label class="ssearch-field">
        <x-lucide-search class="ico" />
        <input type="search" class="input" x-ref="input" x-model="q" autocomplete="off" enterkeyhint="search"
               x-on:focus="open = true" x-on:input="open = true; active = -1"
               x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="enter()"
               placeholder="Cari toko, pemilik, atau alamat…" aria-label="Cari toko"
               role="combobox" aria-autocomplete="list" aria-controls="{{ $uid }}" :aria-expanded="(open && !!q).toString()">
    </label>
    <div class="ssearch-backdrop" x-show="open && q" x-cloak x-transition.opacity x-on:click="close()"></div>
    <div class="ssearch-panel" id="{{ $uid }}" role="listbox" aria-label="Saran toko" x-show="open && q" x-cloak>
        <template x-for="(s, i) in results" :key="s.id">
            <button type="button" class="ssearch-opt" role="option" :class="{ 'is-active': i === active }" :aria-selected="(i === active).toString()"
                    :data-offline="'toko:' + s.id" x-on:click="choose(s)">
                <span class="ssearch-name" x-html="mark(s.name)"></span>
                <small x-html="mark([s.owner, s.sub].filter(Boolean).join(' · '))"></small>
                <span class="ssearch-days" x-text="s.days || 'Belum dijadwalkan'" :class="{ 'is-none': !s.days }"></span>
            </button>
        </template>
        <p class="ssearch-empty" x-show="!results.length">Tidak ada toko untuk "<span x-text="q"></span>". Coba nama pemilik atau jalan.</p>
    </div>
</div>
