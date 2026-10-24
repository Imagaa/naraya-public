<?php

// Business switches. All default to closed:
// the gudang portal is off while sales record their own pickups and admin
// records goods in. The marketplace is open again (2026-10-10) for browsing
// and Investasi, but online buying stays on hold until purchasing opens.
return [
    'marketplace_open' => (bool) env('MARKETPLACE_OPEN', false),
    'marketplace_purchasing_open' => (bool) env('MARKETPLACE_PURCHASING_OPEN', false),
    // Grosir (the Naraya line) is "sedang dikembangkan" on the marketplace and the sales app (2026-10-11); admin keeps both lines.
    'grosir_open' => (bool) env('GROSIR_OPEN', false),
    // The sandbox server (naraya-coba.example.com, docker-compose.sandbox.yml): made-up data that can be reset from its
    // admin (`sandbox:reset`), a "MODE COBA" ribbon on every page. Never true on production.
    'sandbox' => (bool) env('SANDBOX', false),
    // Where stores transfer to (sales notes, titip jual); since 2026-10-12 the only payments are cash and this transfer.
    'transfer_to' => env('TRANSFER_TO', 'BNI 0000000000 a.n. Nama Pemilik'),

    // Investasi Naraya. ponytail: config, not an admin screen; add one when the terms change.
    'investment' => [
        'package_price' => 700000,
        'monthly_return' => 28000,
        'months' => 12,
        // Where stores send a transfer/QRIS payment, shown on the Investasi page (e.g. "BCA 1234567890 a.n. PT Naraya").
        'transfer_to' => env('INVESTMENT_TRANSFER_TO', env('TRANSFER_TO', 'BNI 0000000000 a.n. Nama Pemilik')),
    ],
];
