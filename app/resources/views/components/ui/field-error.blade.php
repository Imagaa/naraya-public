@props(['name'])

@error($name)<span {{ $attributes->merge(['class' => 'field-error']) }}>{{ $message }}</span>@enderror
