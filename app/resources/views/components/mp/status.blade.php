@props(['tone' => 'neutral', 'icon' => null])
<span {{ $attributes->merge(['class' => 'status status-'.$tone]) }}>@if($icon)<x-dynamic-component :component="'lucide-'.$icon" class="ico" />@endif{{ $slot }}</span>
