<?php

use Illuminate\Support\Facades\File;

it('keeps layout styling out of sales views and components', function () {
    $files = collect(File::allFiles(resource_path('views/livewire/sales')))
        ->merge(File::allFiles(resource_path('views/components/sales')));

    $offenders = $files->flatMap(function ($file) {
        preg_match_all('/style="([^"]*)"/', $file->getContents(), $matches);

        return collect($matches[1])
            ->reject(fn (string $style) => str_contains($style, '{{')) // dynamic values are allowed
            ->map(fn (string $style) => $file->getRelativePathname().': '.$style);
    })->values()->all();

    expect($offenders)->toBe([]);
});
