{{-- A quantity in an offline form (partials/offline.blade.php): − [n] +, in pcs for the count of what is left at the store, in whole units (lembar) otherwise. --}}
<span class="unit-step">
    <span class="step step-input">
        <button type="button" :aria-label="'Kurangi ' + product(id).name" x-on:click="step1('{{ $group }}', id, -1)"><x-lucide-minus class="ico" /></button>
        <input type="number" min="0" step="1" inputmode="numeric" placeholder="0" x-model="rows['{{ $group }}'][id].n" :aria-label="'Jumlah ' + product(id).name">
        <button type="button" :aria-label="'Tambah ' + product(id).name" x-on:click="step1('{{ $group }}', id, 1)"><x-lucide-plus class="ico" /></button>
    </span>
    <small x-text="'{{ $group }}' === 'remaining' ? 'pcs' : product(id).unit"></small>
</span>
