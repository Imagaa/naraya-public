{{--
    A save button that only works once its form holds unsaved changes.
    `target`: the form's properties (comma list) watched by wire:dirty; omit it
    when the whole component is the form. `changed`: unsaved state wire:dirty
    can't see, e.g. a photo already uploaded to the server but not saved yet.
    The wire:key flips with `changed` so Livewire swaps the element instead of
    re-disabling it after a round trip.
--}}
@props(['target' => null, 'changed' => false, 'variant' => 'primary', 'type' => 'submit'])

<button type="{{ $type }}" data-save {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}
    @if($changed) wire:key="save-on-{{ $target }}" @else wire:key="save-off-{{ $target }}" disabled wire:dirty.remove.attr="disabled" @if($target) wire:target="{{ $target }}" @endif @endif
>{{ $slot }}</button>
