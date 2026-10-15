<?php

test('returns a successful response', function () {
    $response = $this->get(route('central.login'));

    $response->assertStatus(200);
});
