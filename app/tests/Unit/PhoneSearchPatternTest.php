<?php

use App\Support\Phone;

it('matches stored 62… and 0… numbers whichever way the phone was typed', function (string $typed) {
    expect(Phone::searchPattern($typed))->toBe('%81234567890%');
})->with(['0812-3456-7890', '+62 812 3456 7890', '6281234567890', '81234567890']);

it('ignores short or non-numeric terms', function (string $typed) {
    expect(Phone::searchPattern($typed))->toBeNull();
})->with(['1234', 'Toko Sinar', '']);
