<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One placed keypad furni and its current two-digit access code. Rows are
 * created and re-rolled by the emulator's KeypadManager on every keypad open;
 * this model exists so staff can view and override codes from the parent
 * Heist's config page (HeistKeypadsRelationManager).
 *
 * Linked to its parent {@link Heist} by {@code item_base_id} (the keypad
 * furni's base, which is also the Heist's key).
 *
 * @property int $id
 * @property int $placed_item_id
 * @property int $item_base_id
 * @property int $room_id
 * @property int $next_key
 * @property-read Heist|null $heist
 */
class HeistKeypad extends Model
{
    protected $table = 'rp_heist_keypads';

    protected $guarded = [];

    public function heist(): BelongsTo
    {
        return $this->belongsTo(Heist::class, 'item_base_id', 'item_base_id');
    }
}
