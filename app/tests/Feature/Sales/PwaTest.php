<?php

it('ships an installable manifest whose icons all exist', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['theme_color'])->toBe('#F0B90B')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->filter()->all())->toContain('maskable')
        ->and(collect($manifest['shortcuts'])->pluck('url')->all())->toBe(['/absensi', '/pesanan', '/kunjungan']);

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
        [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/')));
        expect("{$w}x{$h}")->toBe($icon['sizes']);
    }
    expect(public_path('icons/badge-96.png'))->toBeFile();
});

it('serves the offline fallback page without login', function () {
    $this->get(salesUrl('/offline'))->assertOk()->assertSee('Sedang offline');
});

it('versions the service worker cache and handles push and notification clicks', function () {
    $sw = file_get_contents(public_path('service-worker.js'));

    expect($sw)->toContain('CACHE_VERSION')
        ->toContain("addEventListener('push'")
        ->toContain("addEventListener('notificationclick'")
        ->toContain('setAppBadge')
        ->toContain("'/offline'");
});
