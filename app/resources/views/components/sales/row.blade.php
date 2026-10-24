{{-- Inner parts are spans/small so the row is also valid inside a <button>. --}}
@props(['href' => null, 'as' => 'div'])
@php($tag = $href ? 'a' : $as)

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'sales-row']) }}>
    @isset($lead)
        <span class="sales-row-lead">{{ $lead }}</span>
    @endisset
    <span class="sales-row-main">
        {{ $slot }}
        @isset($meta)
            <small class="sales-row-meta">{{ $meta }}</small>
        @endisset
    </span>
    @isset($trail)
        <span class="sales-row-trail">{{ $trail }}</span>
    @endisset
</{{ $tag }}>
