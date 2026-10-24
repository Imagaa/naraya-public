@props(['steps'])

<ol {{ $attributes->class('adm-timeline') }}>
    @foreach($steps as $step)
        <li @class(['is-done' => $step['done']])>
            <span class="adm-timeline-dot" aria-hidden="true"></span>
            <div>
                <strong>{{ $step['label'] }}</strong>
                @if(($step['time'] ?? null) || ($step['by'] ?? null))
                    <small>{{ ($step['time'] ?? null)?->wib()->locale('id')->translatedFormat('j M Y H.i') }}{{ ($step['time'] ?? null) && ($step['by'] ?? null) ? ' · ' : '' }}{{ $step['by'] ?? '' }}</small>
                @endif
                @if($step['note'] ?? null)<small>{{ $step['note'] }}</small>@endif
                @if($step['photo'] ?? null)<a href="{{ $step['photo'] }}" target="_blank" rel="noopener">Lihat foto</a>@endif
            </div>
        </li>
    @endforeach
</ol>
