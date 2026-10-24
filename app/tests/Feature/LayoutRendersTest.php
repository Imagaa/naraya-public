<?php

it('renders the marketplace layout with the given slot content', function () {
    $html = view('layouts.marketplace', ['slot' => 'hello'])->render();

    expect($html)->toContain('hello');
});
