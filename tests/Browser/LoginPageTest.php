<?php

/*
 * Foundation browser test: proves the Pest browser suite drives a real page
 * in a real browser. The login page is public, so it needs no seeded data —
 * see tests/Pest.php for why browser tests omit RefreshDatabase.
 */

test('the login page renders in a real browser', function () {
    $page = visit('/login');

    $page->assertSee('Log in to your account')
        ->assertPresent('input[name="email"]')
        ->assertPresent('input[name="password"]')
        ->assertNoJavaScriptErrors();
});
