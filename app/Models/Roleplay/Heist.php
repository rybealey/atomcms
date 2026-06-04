<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A heist: a name, success/timing tuning, a weighted reward table, and a set
 * of furnitures (each with a role: keypad / search / pickup). Furnitures
 * replaced the old single item_base_id field. Keypad furnitures carry their
 * own per-placement access code on the furniture row.
 *
 * @property int $id
 * @property string $name
 * @property int $find_chance_pct
 * @property int $cooldown_seconds
 * @property int $search_seconds
 * @property-read Collection<int, HeistFurniture> $furnitures
 * @property-read Collection<int, HeistReward> $rewards
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
}
