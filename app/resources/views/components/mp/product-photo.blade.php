@props(['product'])

@if($product->photo_path)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($product->photo_path) }}" alt="{{ $product->name }}" loading="lazy">
@else
    <x-dynamic-component :component="'lucide-'.($product->category?->icon ?: 'package')" class="ph-icon" aria-hidden="true" />
@endif
