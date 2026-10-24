@php
    $rp = fn (int $n) => number_format($n, 0, ',', '.');
    $methods = ['cash' => 'Tunai', 'qris' => 'QRIS', 'gopay' => 'GoPay'];
    $tz = config('app.business_timezone');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Nota {{ $order->receiptNumber() }}</title>
    {{-- Self-contained so it prints the same everywhere: black on white, monospace,
         tables for the two-column rows (dompdf renders them too). The print page
         takes its paper size from the printer/device; the PDF is an 80mm roll. --}}
    <style>
        @if($pdf)
            @page { margin: 0; }
        @else
            @page { size: auto; margin: 0; }
        @endif
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font: 12px/1.35 "DejaVu Sans Mono", "Courier New", Courier, monospace; }
        @if($pdf)
            .paper { padding: 10pt 8pt; }
        @else
            .paper { width: 100%; padding: 4mm 3mm; }
        @endif
        .c { text-align: center; }
        .r-logo { display: block; width: 62%; max-width: 220px; height: auto; margin: 0 auto 6px; }
        .r { text-align: right; white-space: nowrap; }
        .b { font-weight: bold; }
        .big { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .rule { margin: 6px 0; border: 0; border-top: 1px dashed #000; }
        .rule2 { margin: 6px 0; border: 0; border-top: 2px solid #000; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .item td { padding-top: 4px; }
        .stamp { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 2px solid #000; font-weight: bold; letter-spacing: 2px; }
        p { margin: 0; }
        @unless($pdf)
            .tools { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; padding: 12px; }
            .tools a, .tools button { font: 600 14px system-ui, sans-serif; padding: 10px 16px; border-radius: 10px; border: 1px solid #C9CDD3; background: #fff; color: #1E2329; text-decoration: none; cursor: pointer; }
            .tools .primary { background: #F0B90B; border-color: #F0B90B; }
            @media screen {
                body { background: #F5F6F8; }
                .paper { max-width: 340px; margin: 0 auto 24px; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
            }
            @media print {
                .tools { display: none; }
            }
        @endunless
    </style>
</head>
<body>
    @unless($pdf)
        <nav class="tools" aria-label="Aksi nota">
            <a href="{{ $backUrl }}">Kembali</a>
            @if($canPrint)
                <button type="button" class="primary" onclick="window.print()">Cetak Nota</button>
            @endif
            <a href="{{ $pdfUrl }}" @class(['primary' => ! $canPrint])>Unduh PDF</a>
        </nav>
    @endunless

    <main class="paper">
        <div class="c">
            {{-- dompdf reads a local PNG reliably; the browser view uses the vector --}}
            <img class="r-logo" src="{{ $pdf ? public_path('brand/png/naraya-grosir-horizontal-black.png') : asset('brand/naraya-grosir-horizontal-black.svg') }}" alt="naraya grosir">
            <p class="big">{{ config('receipt.name') }}</p>
            <p>{{ config('receipt.tagline') }}</p>
            <p>{{ config('receipt.address') }}</p>
            <p>Telp {{ config('receipt.phone') }}</p>
        </div>

        <hr class="rule2">
        <p class="c b">NOTA PENJUALAN</p>
        <table>
            <tr><td>No</td><td class="r">{{ $order->receiptNumber() }}</td></tr>
            <tr><td>Tanggal</td><td class="r">{{ $order->completed_at->timezone($tz)->format('d/m/Y H:i') }}</td></tr>
            <tr><td>Pesanan</td><td class="r">#{{ $order->id }}</td></tr>
            @if($order->salesUser)
                <tr><td>Sales</td><td class="r">{{ $order->salesUser->name }}</td></tr>
            @endif
        </table>
        <hr class="rule">
        <p class="b">{{ $order->buyer->name }}</p>
        <p>{{ $order->delivery_address }}</p>
        <hr class="rule">

        <table>
            @foreach($order->items as $item)
                <tr class="item"><td colspan="2">{{ $item->product_name }}</td></tr>
                <tr>
                    <td>{{ $item->quantity }} x {{ $rp($item->price) }}</td>
                    <td class="r">{{ $rp($item->subtotal) }}</td>
                </tr>
            @endforeach
        </table>

        <hr class="rule">
        <table>
            <tr><td>Subtotal</td><td class="r">{{ $rp($order->subtotal) }}</td></tr>
            @if($order->credit_applied > 0)
                <tr><td>Kredit toko</td><td class="r">-{{ $rp($order->credit_applied) }}</td></tr>
            @endif
        </table>
        <hr class="rule2">
        <table>
            <tr class="b"><td>TOTAL</td><td class="r">Rp {{ $rp($order->total) }}</td></tr>
            <tr><td>Bayar</td><td class="r">{{ $methods[$order->payment_method] ?? strtoupper($order->payment_method) }}</td></tr>
        </table>
        <div class="c"><span class="stamp">LUNAS</span></div>

        <hr class="rule">
        <p class="c">{{ config('receipt.footer') }}</p>
        <p class="c">Dicetak {{ now($tz)->format('d/m/Y H:i') }}</p>
    </main>
</body>
</html>
