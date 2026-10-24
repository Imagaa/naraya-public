@props(['label'])
{{-- Camera photo in an offline form (partials/offline.blade.php): watermarked and compressed like online proofs, kept on the phone. --}}
<div class="field">
    <label>{{ $label }}</label>
    <label class="btn btn-secondary btn-block" x-show="! preview">
        <x-lucide-camera class="ico" /> Ambil foto
        <input type="file" accept="image/*" capture="environment" x-on:change="capture" hidden>
    </label>
    <template x-if="preview">
        <div class="offline-photo"><img :src="preview" alt="Pratinjau foto"><button type="button" class="btn btn-secondary" x-on:click="preview = null; photo = null">Ambil ulang</button></div>
    </template>
    <p class="sales-note" x-show="busy">Memproses foto…</p>
</div>
