<?php

use Database\Seeders\SeoSeeder;

test('returns a successful response', function () {
    $this->seed(SeoSeeder::class);
    $response = $this->get(route('home'));

    $response->assertOk();
});
