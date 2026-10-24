<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an uploaded file on the s3 disk', function () {
    Storage::fake('s3');

    $file = UploadedFile::fake()->image('produk.jpg');
    $path = $file->store('produk', 's3');

    Storage::disk('s3')->assertExists($path);
});
