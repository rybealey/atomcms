<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Model;

/**
 * One placed keypad furni and its current two-digit access code. Rows are
 * created and re-rolled by the emulator's KeypadManager on every keypad open;
 * this model exists so staff can view and override codes in
 * "Roleplay > Heist Keypads".
 *
 * @property int $id
 * @property int $placed_item_id
 * @property int $room_id
 * @property int $next_key
 */
class HeistKeypad extends Model
{
    protected $table = 'rp_heist_keypads';

    protected $guarded = [];
}
