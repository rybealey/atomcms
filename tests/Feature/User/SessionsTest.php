<?php

use App\Models\User;
use App\Models\User\WebsiteLoginLog;

beforeEach(function () {
    installHotel();
});

test('a failed log in is recorded in the history', function () {
    $user = User::factory()->create(['username' => 'Marlowe']);

    $this->post(route('login'), ['username' => 'Marlowe', 'password' => 'not-the-password']);

    $log = WebsiteLoginLog::query()->where('user_id', $user->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->succeeded)->toBeFalse()
        ->and($log->method)->toBe('wrong-password');
});

test('a successful log in is recorded in the history', function () {
    $user = User::factory()->create(['username' => 'Marlowe', 'password' => bcrypt('Foggy-tuesday-7!')]);

    $this->post(route('login'), ['username' => 'Marlowe', 'password' => 'Foggy-tuesday-7!']);

    expect(WebsiteLoginLog::query()->where('user_id', $user->id)->where('succeeded', true)->exists())->toBeTrue();
});

test('the sessions page shows the history and warns about failed attempts', function () {
    $user = User::factory()->create();

    WebsiteLoginLog::query()->create([
        'user_id' => $user->id,
        'username' => $user->username,
        'ip_address' => '203.0.113.9',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        'succeeded' => false,
        'method' => 'wrong-password',
    ]);

    $this->actingAs($user)
        ->get(route('settings.session-logs'))
        ->assertOk()
        ->assertSee('failed log in attempt this week')
        ->assertSee('203.0.113.xx')
        ->assertDontSee('203.0.113.9');
});

test('the current session cannot log itself out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('settings.sessions.destroy', session()->getId()))
        ->assertStatus(422);
});
