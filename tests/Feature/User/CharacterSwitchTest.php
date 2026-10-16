<?php

use App\Models\User;

beforeEach(function () {
    installHotel();
});

test('an account can switch between its own characters', function () {
    $root = User::factory()->create();
    $child = User::factory()->create(['parent_id' => $root->id]);

    $this->actingAs($root)
        ->from(route('me.show'))
        ->post(route('characters.switch', $child))
        ->assertRedirect(route('me.show'));

    expect((int) $root->fresh()->active_character_id)->toBe($child->id)
        ->and($root->fresh()->activeCharacter()->id)->toBe($child->id);

    // Back to the root clears the pointer rather than storing the root's own id.
    $this->actingAs($root)->post(route('characters.switch', $root));

    expect($root->fresh()->active_character_id)->toBeNull();
});

test('a character on another account cannot be switched to', function () {
    $root = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($root)
        ->post(route('characters.switch', $stranger))
        ->assertForbidden();

    expect($root->fresh()->active_character_id)->toBeNull();
});
