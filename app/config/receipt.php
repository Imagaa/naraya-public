<?php

// Header and footer printed on every order receipt (nota). Placeholders until the business details are final.
return [
    'name' => env('RECEIPT_NAME', 'NARAYA'),
    'tagline' => env('RECEIPT_TAGLINE', 'Grosir Kebutuhan Warung'),
    'address' => env('RECEIPT_ADDRESS', 'Jl. Contoh No. 1, Jakarta'),
    'phone' => env('RECEIPT_PHONE', '0812-0000-0000'),
    'footer' => env('RECEIPT_FOOTER', 'Terima kasih telah berbelanja di Naraya'),
];
