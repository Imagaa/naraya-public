@props(['buyer', 'size' => 40])
@php($url = $buyer->buyerProfile?->storePhotoUrl())
@if($url)
    <img src="{{ $url }}" alt="Foto {{ $buyer->name }}" width="{{ $size }}" height="{{ $size }}" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:8px;object-fit:cover;flex:none">
@else
    <span aria-hidden="true" style="width:{{ $size }}px;height:{{ $size }}px;border-radius:8px;background:#F5F6F8;color:#474D57;display:grid;place-items:center;font-weight:700;flex:none">{{ mb_strtoupper(mb_substr($buyer->name, 0, 1)) }}</span>
@endif
