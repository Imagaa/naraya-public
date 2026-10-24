{{--
    Camera-only proof photo: the file input forces the device camera (no
    gallery picker) and, on capture, JS stamps a location+timestamp
    watermark into the image before uploading it to the given Livewire
    property via $wire.upload — see resources/js/delivery-photo.js.
--}}
@props(['wireModel', 'label' => 'Foto'])

<div class="field" x-data="deliveryPhoto('{{ $wireModel }}')">
    <label>{{ $label }}</label>

    <div x-show="!preview">
        <label class="btn btn-secondary btn-block" style="text-align:center;cursor:pointer">
            <x-dynamic-component component="lucide-camera" style="width:16px;height:16px;display:inline-block;vertical-align:-3px;margin-right:4px" />
            Ambil Foto
            <input type="file" accept="image/*" capture="environment" @change="capture" style="display:none">
        </label>
    </div>

    <div x-show="preview" style="display:flex;flex-direction:column;gap:6px">
        <img :src="preview" style="max-width:100%;border-radius:8px" alt="Pratinjau foto">
        <button type="button" class="btn btn-secondary" @click="retake" :disabled="uploading">Ambil Ulang</button>
    </div>

    <p x-show="uploading" class="text-muted" style="font-size:12px;margin-top:6px">Memproses &amp; mengunggah...</p>
    <p x-show="errorMsg" style="color:var(--color-danger);font-size:12px;margin-top:6px" x-text="errorMsg"></p>
</div>
