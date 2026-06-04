<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A heist: a name, success/timing tuning, a weighted reward table, and a set
 * of furnitures (each with a role: keypad / search / pickup). Furnitures
 * replaced the old single item_base_id field.
 *
 * @property int $id
 * @property string $name
 * @property int $find_chance_pct
 * @property int $cooldown_seconds
 * @property int $search_seconds
 * @property-read Collection<int, HeistFurniture> $furnitures
 * @property-read Collection<int, HeistReward> $rewards
 * @property-read Collection<int, HeistKeypad> $keypads
 */
class Heist extends Model
{
    protected $table = 'rp_heists';

    protected $guarded = [];

    public function furnitures(): HasMany
    {
        return $this->hasMany(HeistFurniture::class, 'heist_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(HeistReward::class, 'heist_id');
    }

    /**
     * Placed keypads for this heist, joined through its furnitures: a keypad's
     * code row (rp_heist_keypads) links by item_base_id to a furniture of this
     * heist. Only keypad furni ever get code rows, so this naturally yields
     * just this heist's keypad placements.
     */
    public function keypads(): HasManyThrough
    {
        return $this->hasManyThrough(
            HeistKeypad::class,
            HeistFurniture::class,
            'heist_id',      // FK on rp_heist_furnitures -> rp_heists.id
            'item_base_id',  // FK on rp_heist_keypads -> furniture's item_base_id
            'id',            // local key on rp_heists
            'item_base_id',  // local key on rp_heist_furnitures
        );
    }
}
