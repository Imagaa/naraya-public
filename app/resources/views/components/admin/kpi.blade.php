@props(['label', 'value', 'note' => null, 'progress' => null])

<div {{ $attributes->class('adm-kpi') }}>
    <span class="adm-kpi-label">{{ $label }}</span>
    <strong class="adm-kpi-value">{{ $value }}</strong>
    @if($note)<span class="adm-kpi-note">{{ $note }}</span>@endif
    @if($progress !== null)
        <progress class="adm-kpi-bar" max="100" value="{{ $progress }}">{{ $progress }}%</progress>
    @endif
</div>
