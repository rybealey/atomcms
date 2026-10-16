<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * pixelrp: pick which character the website shows and the next `/game` load
 * enters as. Only `active_character_id` on the account root changes; the
 * emulator reads it when it issues the next ticket (NitroController), and a
 * character already online on this account is disconnected by the hotel's
 * own one-character-at-a-time rule when the new one logs in.
 */
class CharacterSwitchController extends Controller
{
    public function __invoke(Request $request, User $character): RedirectResponse
    {
        $root = AuthenticatedUser::from($request)->accountRoot();

        // Only a character on THIS account: the root itself, or one of its children.
        abort_unless((int) $character->id === (int) $root->id || (int) $character->parent_id === (int) $root->id, 403);

        $root->update(['active_character_id' => (int) $character->id === (int) $root->id ? null : $character->id]);

        return back()->with('success', __('You are now :name.', ['name' => $character->username]));
    }
}
