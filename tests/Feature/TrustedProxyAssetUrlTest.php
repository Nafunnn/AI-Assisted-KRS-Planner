<?php

test('generates https asset urls when a proxy forwards https', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ])->get(route('home'));

    $response->assertOk();
    $response->assertSee('width=device-width, initial-scale=1, viewport-fit=cover', false);
    $response->assertSee('https://localhost/build/', false);
    $response->assertDontSee('http://localhost/build/', false);
});
