<?php

use App\Models\User;

beforeEach(function () {
    installHotel();
});

test('the rules page renders for guests', function () {
    $this->get(route('help-center.rules.index'))->assertOk();
});

test('public responses include browser security headers', function () {
    $this->get(route('help-center.rules.index'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'")
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups')
        ->assertHeader('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('secure production responses enable transport security', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->withHeader('X-Forwarded-Proto', 'https')
        ->get(route('help-center.rules.index'))
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('switching language stores the locale', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('me.show'))
        ->get(route('language.select', 'en'))
        ->assertRedirect();

    expect(session('locale'))->toBe('en');
});
