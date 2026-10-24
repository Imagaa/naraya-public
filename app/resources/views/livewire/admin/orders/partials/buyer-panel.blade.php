@php
    $profile = $order->buyer->buyerProfile;
    $phone = \App\Support\Phone::normalize($profile?->phone);
    $lat = (float) ($order->delivery_lat ?: $profile?->latitude);
    $lng = (float) ($order->delivery_lng ?: $profile?->longitude);
@endphp
<x-admin.panel title="Toko" class="adm-o-1">
    <p class="adm-store"><a href="{{ route('admin.buyers.edit', $order->buyer) }}">{{ $order->buyer->name }}</a>
        @if($profile?->owner_name)<span class="adm-muted">Pemilik: {{ $profile->owner_name }}</span>@endif
    </p>
    @if($phone)
        <div class="adm-contact">
            <span class="adm-num">0{{ substr($phone, 2) }}</span>
            <a class="btn btn-secondary" href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener"><x-lucide-message-circle width="16" height="16" /> WA</a>
            <a class="btn btn-secondary" href="tel:+{{ $phone }}"><x-lucide-phone width="16" height="16" /> Telp</a>
        </div>
    @endif
    <p>{{ $order->delivery_address }}</p>
    @if($lat && $lng)
        <a class="adm-more" href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener">Buka di peta</a>
    @endif
</x-admin.panel>
