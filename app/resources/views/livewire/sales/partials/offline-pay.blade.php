{{-- Payment in an offline form (partials/offline.blade.php): cash, or transfer with a proof photo. --}}
<fieldset class="check-group">
    <legend>Dibayar dengan</legend>
    @foreach(\App\Enums\NotePaymentMethod::choices() as $m)
        <x-ui.checkbox chip type="radio" :label="$m->label()" name="offline-method" value="{{ $m->value }}" x-model="method" />
    @endforeach
</fieldset>
<template x-if="method !== 'cash'">
    <div>
        <p class="sales-note">Transfer ke <b x-text="snap.transferTo"></b>.</p>
        <x-ui.offline-photo label="Foto bukti transfer" />
    </div>
</template>
<p class="sales-note" x-show="method === 'cash'">Uang tunai Anda setorkan ke admin lewat menu Setoran.</p>
