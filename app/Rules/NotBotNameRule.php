<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Support\Str;

/**
 * pixelrp: no player may be named "bot-..." (Twist, 2026-10-22). The hotel
 * shows every bot as "bot-Name", so a name that starts that way is a bot's -
 * which is what lets the HUD target's "x" type a bot's name into a command.
 * The emulator refuses the same names in game (BotNames).
 */
class NotBotNameRule implements InvokableRule
{
    public function __invoke(string $attribute, mixed $value, Closure $fail)
    {
        if (is_string($value) && Str::startsWith(Str::lower($value), 'bot-')) {
            $fail(__('Names can\'t start with "bot-".'));
        }
    }
}
