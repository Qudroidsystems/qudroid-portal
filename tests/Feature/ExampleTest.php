<?php

test('guests are sent to the sign-in page', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/login')->assertOk()->assertSee(config('qudroid.name'));
});
