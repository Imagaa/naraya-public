<?php

// Admin on the phone: one phone layer for every page, desktop untouched.
it('loads the admin phone layer last, after every admin component', function () {
    $css = file_get_contents(resource_path('css/admin.css'));

    expect($css)->toContain("@import './admin/phone.css';")
        ->and(strrpos($css, "@import './admin/phone.css';"))->toBeGreaterThan(strrpos($css, "@import './admin/components.css';"));
});

it('keeps every phone rule behind the phone breakpoint or a touch pointer', function () {
    $css = file_get_contents(resource_path('css/admin/phone.css'));
    // strip comments, then every top-level block must be an @media query, so the desktop never changes
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    preg_match_all('/^([^\s}][^{]*)\{/m', $css, $tops);

    expect($tops[1])->not->toBeEmpty();
    foreach ($tops[1] as $selector) {
        expect(trim($selector))->toStartWith('@media');
    }
});

it('labels admin data tables so the phone can stack them as cards', function () {
    expect(file_get_contents(resource_path('js/admin.js')))->toContain("import './admin-table-labels';")
        ->and(file_get_contents(resource_path('js/admin-table-labels.js')))->toContain('data-label');
});
