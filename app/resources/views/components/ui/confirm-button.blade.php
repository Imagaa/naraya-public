{{--
    A button that asks first, in the app's own dialog (not the browser's confirm()). `action` is the Livewire call
    the "yes" button makes, e.g. action="save" or action="cancelDayOff(5)".
--}}
@props(['action', 'message', 'yes' => 'Ya, lanjutkan', 'variant' => 'primary'])

<span class="confirm" x-data>
    <x-ui.button :variant="$variant" type="button" x-on:click="$refs.d.showModal()" {{ $attributes }}>{{ $slot }}</x-ui.button>
    <dialog x-ref="d" class="confirm-dialog" wire:ignore.self x-on:click.self="$el.close()">
        <p>{{ $message }}</p>
        <div class="confirm-actions">
            <button type="button" class="btn btn-secondary" x-on:click="$refs.d.close()">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="{{ $action }}" x-on:click="$refs.d.close()">{{ $yes }}</button>
        </div>
    </dialog>
</span>
