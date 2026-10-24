@if($canCancel)
    {{-- wire:ignore.self keeps the dialog open across the re-render that shows a validation error. --}}
    <dialog id="cancel-dialog" class="adm-dialog" wire:ignore.self x-data x-on:order-cancelled.window="$el.close()">
        <form wire:submit="cancel">
            <h2>Batalkan pesanan #{{ $order->id }}?</h2>
            <fieldset>
                <legend>Alasan</legend>
                @foreach(\App\Enums\CancelReason::cases() as $reason)
                    <label class="adm-radio"><input type="radio" value="{{ $reason->value }}" wire:model="cancelReason"> {{ $reason->label() }}</label>
                @endforeach
            </fieldset>
            @error('cancelReason')<p class="text-danger">{{ $message }}</p>@enderror
            <label class="field">Catatan
                <textarea class="input" rows="3" wire:model="cancelNote" placeholder="Wajib bila memilih Lainnya"></textarea>
            </label>
            @if($cancelEffects)
                <ul class="adm-effects">
                    @foreach($cancelEffects as $effect)<li>{{ $effect }}</li>@endforeach
                </ul>
            @endif
            @error('cancel')<p class="text-danger">{{ $message }}</p>@enderror
            <div class="adm-dialog-actions">
                <button type="button" class="btn btn-secondary" @click="$el.closest('dialog').close()">Kembali</button>
                <button type="submit" class="btn adm-btn-danger">Batalkan pesanan</button>
            </div>
        </form>
    </dialog>
@endif
