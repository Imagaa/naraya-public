@props(['href', 'label', 'meta' => '', 'count', 'severity' => 'idle'])

<a href="{{ $href }}" {{ $attributes->class(['adm-queue-row', 'is-'.$severity]) }}>
    <span class="adm-dot" aria-hidden="true"></span>
    <span class="adm-queue-label">
        {{ $label }}
        @if($severity !== 'idle')<span class="adm-sr">{{ $severity === 'late' ? 'Lewat batas' : 'Mendekati batas' }}</span>@endif
        @if($meta)<small>{{ $meta }}</small>@endif
    </span>
    <span class="adm-queue-count">{{ $count }}</span>
    <x-lucide-chevron-right width="16" height="16" aria-hidden="true" />
</a>
