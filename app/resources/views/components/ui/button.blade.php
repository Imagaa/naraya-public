@props(['variant' => 'primary', 'type' => 'button', 'tag' => 'button', 'disabled' => false])

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}>{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}
    >{{ $slot }}</button>
@endif
