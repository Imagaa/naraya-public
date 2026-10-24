{{-- Business line switch (naraya grosir | naraya toys) with the brand-kit logos: lockups, or the 32 px line
     symbols where space is tight (symbols). Two native submit buttons, the active one pressed; a closed line
     (grosir while it is being built) is a disabled button marked "Sedang dikembangkan", after the open ones. --}}
@props(['action', 'symbols' => false])
@php($current = \App\Support\ActiveLine::current())
@php($lines = collect(\App\Enums\BusinessLine::cases())->sortBy(fn ($l) => \App\Support\ActiveLine::isOpen($l) ? 0 : 1))

<form method="POST" action="{{ $action }}" {{ $attributes->class(['line-switch', 'line-switch-logos', 'is-symbols' => $symbols]) }} aria-label="Lini usaha">
    @csrf
    @foreach($lines as $line)
        @if(\App\Support\ActiveLine::isOpen($line))
            <button type="submit" name="line" value="{{ $line->value }}" aria-pressed="{{ $current === $line ? 'true' : 'false' }}" title="{{ $line->label() }}">
                <x-brand.logo :line="$line->brand()" :variant="$symbols ? 'symbol' : 'horizontal'" />
            </button>
        @else
            <button type="button" disabled class="is-soon" title="{{ $line->label() }} grosir — sedang dikembangkan">
                <x-brand.logo :line="$line->brand()" :variant="$symbols ? 'symbol' : 'horizontal'" />
                <small class="line-soon">Sedang dikembangkan</small>
            </button>
        @endif
    @endforeach
</form>
