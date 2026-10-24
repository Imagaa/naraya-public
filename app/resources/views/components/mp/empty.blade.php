@props(['icon' => 'package', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <span class="tile"><x-dynamic-component :component="'lucide-'.$icon" class="ico" /></span>
    <b>{{ $title }}</b>
    @if($text)<p>{{ $text }}</p>@endif
    {{ $slot }}
</div>
