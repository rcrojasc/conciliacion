<?php

test('guest is redirected to login when accessing dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
