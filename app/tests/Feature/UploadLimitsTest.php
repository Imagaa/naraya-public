<?php

// nginx's 1 MB default and PHP's 2 MB default rejected ordinary phone photos
// (413 → "The photo failed to upload") although the app accepts up to 5 MB and
// Livewire's temporary upload up to 12 MB. The server layers must stay above that.

function megabytes(string $value): int
{
    preg_match('/^(\d+)\s*([kmg]?)/i', trim($value), $m);

    return (int) $m[1] * ['' => 1 / 1048576, 'k' => 1 / 1024, 'm' => 1, 'g' => 1024][strtolower($m[2])];
}

it('lets nginx pass a request body larger than the biggest upload Livewire accepts', function (string $template) {
    $conf = file_get_contents(base_path("docker/nginx/{$template}"));

    expect(preg_match('/client_max_body_size\s+(\w+);/', $conf, $m))->toBe(1)
        ->and(megabytes($m[1]))->toBeGreaterThan(12);
})->with(['default.conf.prod.template', 'default.conf.template', 'default.conf']);

it('lets PHP accept a file as large as Livewire\'s 12 MB temporary upload', function () {
    $ini = parse_ini_file(base_path('docker/php-fpm/uploads.ini'));

    expect(megabytes($ini['upload_max_filesize']))->toBeGreaterThanOrEqual(12)
        ->and(megabytes($ini['post_max_size']))->toBeGreaterThan(megabytes($ini['upload_max_filesize']))
        ->and(file_get_contents(base_path('Dockerfile')))->toContain('docker/php-fpm/uploads.ini');
});
