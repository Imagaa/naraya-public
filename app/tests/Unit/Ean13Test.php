<?php

use App\Support\Ean13;

it('accepts valid EAN-13 codes', function (string $code) {
    expect(Ean13::isValid($code))->toBeTrue();
})->with(['8996001600269', '8998009010613', '4006381333931']);

it('rejects bad codes', function (string $code) {
    expect(Ean13::isValid($code))->toBeFalse();
})->with(['8996001600268', '899600160026', 'abcdefghijklm', '']);
