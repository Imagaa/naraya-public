@props(['options', 'active', 'action'])

<div {{ $attributes->class('adm-pills') }} role="group" aria-label="Filter">
    @foreach($options as $key => $option)
        @php([$label, $count] = array_pad((array) $option, 2, null))
        @php($on = (string) $active === (string) $key)
        <button type="button" wire:click="{{ $action }}('{{ $key }}')" class="adm-pill-btn{{ $on ? ' is-active' : '' }}" aria-pressed="{{ $on ? 'true' : 'false' }}">{{ $label }}@if($count !== null) <span>{{ $count }}</span>@endif</button>
    @endforeach
</div>
