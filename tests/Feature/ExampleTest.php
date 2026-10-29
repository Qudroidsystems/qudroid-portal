<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the website is the front page, and sign-in stays at /login', function () {
    $this->get('/')->assertOk()->assertSee('Qlouds');
    $this->get('/login')->assertOk()->assertSee(config('qudroid.name'));
});
