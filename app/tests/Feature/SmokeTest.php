<?php

it('boots the application and serves the welcome route', function () {
    $this->get('http://'.config('app.domain').'/')->assertStatus(200);
});
