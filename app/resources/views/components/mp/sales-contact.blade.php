@php $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''); @endphp
<div {{ $attributes->merge(['class' => 'rep-block']) }}>
    <div class="rep">
        @if($photo)<img class="av av-photo" src="{{ $photo }}" alt="Foto {{ $name }}">@else<span class="av">{{ $initials }}</span>@endif
        <span><b>{{ $name }}</b><span class="num">{{ $phone ?: 'Sales Anda' }}@if($area) · Area {{ $area }}@endif</span></span>
    </div>
    @if($wa)
        <div class="rep-actions">
            <a class="btn btn-secondary btn-sm" href="tel:{{ preg_replace('/\D/', '', $phone) }}"><x-lucide-phone class="ico ico-sm" />Telepon</a>
            <a class="btn btn-ink btn-sm" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><x-lucide-message-circle class="ico ico-sm" />WhatsApp</a>
        </div>
    @endif
</div>
