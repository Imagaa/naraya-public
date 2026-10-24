@props(['phone' => null, 'lat' => null, 'lng' => null, 'address' => null])
@php
    $wa = \App\Support\Phone::normalize($phone);
    $destination = $lat !== null && $lat !== '' && $lng !== null && $lng !== '' ? $lat.','.$lng : $address;
@endphp

@if($wa || $destination)
    <div {{ $attributes->merge(['class' => 'sales-contact']) }}>
        @if($wa)
            <a href="tel:+{{ $wa }}" class="btn btn-secondary"><x-lucide-phone width="16" height="16" /> Telepon</a>
            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-lucide-message-circle width="16" height="16" /> WhatsApp</a>
        @endif
        @if($destination)
            <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ urlencode($destination) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-lucide-navigation width="16" height="16" /> Maps</a>
        @endif
    </div>
@endif
