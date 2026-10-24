@props(['label', 'wireModel', 'value' => null])

<div class="field" x-data="{ display: {{ $value !== null && $value !== '' ? "'".number_format((float) $value, 0, ',', '.')."'" : "''" }} }">
    <label>{{ $label }}</label>
    <div class="admin-currency-input">
        <span class="admin-currency-prefix">Rp</span>
        <input
            type="text" inputmode="numeric" class="input" style="padding-left:34px"
            x-model="display"
            @input="
                let raw = display.replace(/[^0-9]/g, '');
                display = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                $wire.set('{{ $wireModel }}', raw ? parseInt(raw) : null, false);
            "
        >
    </div>
</div>
