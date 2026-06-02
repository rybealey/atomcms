<?php

namespace App\Models\Roleplay;

use App\Models\Game\Furniture\ItemBase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Foundation clone of {@link Bin}. Heists mirror the dumpster-dive data
 * model; the trigger that starts a heist is a future UI component.
 *
 * @property int $id
 * @property int $item_base_id
 * @property string $name
 * @property int $find_chance_pct
 * @property int $cooldown_seconds
 * @property int $search_seconds
 * @property-read ItemBase|null $itemBase
 * @property-read Collection<int, HeistReward> $rewards
 */
class Heist extends Model
{
    protected $table = 'rp_heists';

    protected $guarded = [];

    public function itemBase(): BelongsTo
    {
        return $this->belongsTo(ItemBase::class, 'item_base_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(HeistReward::class, 'heist_id');
    }
}
