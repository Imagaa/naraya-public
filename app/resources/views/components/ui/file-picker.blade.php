{{-- Photo/file picker: the native file input (keyboard, camera capture, Livewire upload) visually hidden inside a
     dashed box. `file` = the Livewire upload property (a file or, with `multiple`, an array) for the "chosen" state
     and preview; `current` = URL of the photo already saved. wire:model and other attributes go on the input. --}}
@props(['label' => 'Pilih foto', 'hint' => null, 'file' => null, 'current' => null])
@php
    $model = $attributes->whereStartsWith('wire:model')->first();
    $files = collect(is_array($file) ? $file : array_filter([$file]));
    $multiple = $attributes->has('multiple');
    $src = $multiple ? null : (rescue(fn () => $files->first()?->temporaryUrl(), null, false) ?? $current);
@endphp

<label @class(['file-pick', 'has-file' => $files->isNotEmpty()])>
    <input type="file" {{ $attributes->merge(['accept' => 'image/*'])->class('visually-hidden') }}>
    @if($src)
        <img src="{{ $src }}" alt="Pratinjau foto">
    @else
        <span class="file-pick-ico"><x-lucide-image-up class="ico" /></span>
    @endif
    <span class="file-pick-text">
        <b>{{ $files->isEmpty() ? $label : ($files->count() > 1 ? $files->count().' foto terpilih' : 'Foto terpilih') }}</b>
        <small @if($model) wire:loading.remove wire:target="{{ $model }}" @endif>{{ $files->isNotEmpty() || $current ? 'Ketuk untuk mengganti' : $hint }}</small>
        @if($model)<small wire:loading wire:target="{{ $model }}">Mengunggah foto…</small>@endif
    </span>
</label>
