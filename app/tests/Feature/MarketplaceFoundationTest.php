<?php

it('loads the marketplace stylesheet in the marketplace and sales layouts only', function () {
    // Sales moved onto the marketplace design system on 2026-10-05; Admin has its own admin.css.
    foreach (['marketplace', 'sales'] as $layout) {
        expect(file_get_contents(resource_path("views/layouts/{$layout}.blade.php")))
            ->toContain('resources/css/marketplace.css')
            ->not->toContain('resources/css/app.css');
    }

});

it('formats rupiah with dot thousands separators', function () {
    $this->blade('@rupiah($v)', ['v' => 109600])->assertSee('Rp 109.600');
    $this->blade('@rupiah($v)', ['v' => 0])->assertSee('Rp 0');
});

it('keeps the hidden attribute stronger than component display rules', function () {
    // Checkout steps and account panels toggle with `hidden`; .two/.panel set
    // display:grid, which would otherwise override the browser's [hidden] rule.
    $css = file_get_contents(resource_path('css/marketplace/base.css'));

    expect(preg_match('/\[hidden\]\s*\{\s*display:\s*none\s*!important;?\s*\}/', $css))->toBe(1);
});
