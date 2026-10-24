{{-- Period filter of the store report pages (HasStorePeriod). --}}
<div class="store-period">
    <x-admin.filter-pills :options="['today' => 'Hari ini', '7d' => '7 hari terakhir', 'month' => 'Bulan ini', 'range' => 'Rentang bulan']" :active="$this->period" action="setPeriod" />
    @if($this->period === 'range')
        <label class="store-period-month">Dari <input type="month" wire:model.live="fromMonth" class="input"></label>
        <label class="store-period-month">sampai <input type="month" wire:model.live="toMonth" class="input"></label>
    @endif
    <span class="store-period-label">{{ $this->periodLabel() }} · {{ $line->label() }}</span>
</div>
