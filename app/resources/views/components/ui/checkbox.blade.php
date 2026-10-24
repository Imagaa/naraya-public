{{-- Native checkbox (or radio, via type) with its label. `chip`: a pill for picking items; `switch`: an on/off toggle (role="switch"). --}}
@props(['label', 'chip' => false, 'switch' => false, 'type' => 'checkbox'])

<label @class(['check', 'check-chip' => $chip, 'check-switch' => $switch])>
    <input type="{{ $type }}" @if($switch) role="switch" @endif {{ $attributes }}>
    <span>{{ $label }}</span>
</label>
