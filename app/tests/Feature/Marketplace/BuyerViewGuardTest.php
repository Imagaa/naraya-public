<?php

use Illuminate\Support\Facades\File;

/** @return array<string, string> view path => source, for every buyer-facing view */
function buyerViews(): array
{
    $files = [
        ...File::allFiles(resource_path('views/livewire/marketplace')),
        ...File::allFiles(resource_path('views/components/mp')),
        new SplFileInfo(resource_path('views/livewire/auth/login.blade.php')),
    ];

    return collect($files)
        ->mapWithKeys(fn ($f) => [str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $f->getPathname()) => file_get_contents($f->getPathname())])
        ->all();
}

it('keeps buyer views free of inline styles other than data custom properties', function () {
    foreach (buyerViews() as $path => $src) {
        preg_match_all('/style="([^"]*)"/', $src, $m);

        foreach ($m[1] as $style) {
            expect(preg_match('/^\s*(--[\w-]+\s*:[^;]+;?\s*)+$/', $style))->toBe(1, "{$path}: style=\"{$style}\"");
        }
    }
});

it('keeps buyer views free of emoji and hand-written svg icons', function () {
    foreach (buyerViews() as $path => $src) {
        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src))->toBe(0, "{$path} has an emoji")
            ->and(str_contains($src, '<svg'))->toBeFalse("{$path} has a raw <svg>");
    }
});

it('keeps buyer views free of tables', function () {
    foreach (buyerViews() as $path => $src) {
        expect(str_contains($src, '<table') || str_contains($src, 'x-ui.table'))->toBeFalse("{$path} uses a table");
    }
});

it('paginates buyer lists with the marketplace pager only', function () {
    // Livewire's default pager is Tailwind-styled; marketplace.css has no
    // Tailwind utilities, so its arrow icons render at full width.
    foreach (buyerViews() as $path => $src) {
        expect(preg_match('/->links\(\s*\)/', $src))->toBe(0, "{$path} uses the default pager");
    }
});
