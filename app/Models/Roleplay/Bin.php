<?php

namespace App\Models\Roleplay;

use App\Models\Game\Furniture\ItemBase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $item_base_id
 * @property string $name
 * @property int $find_chance_pct
 * @property int $cooldown_seconds
 * @property int $search_seconds
 * @property-read ItemBase|null $itemBase
 * @property-read Collection<int, BinReward> $rewards
 */
class Bin extends Model
{
    protected $table = 'rp_bins';

    protected $guarded = [];

    public function itemBase(): BelongsTo
    {
        return $this->belongsTo(ItemBase::class, 'item_base_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(BinReward::class, 'bin_id');
    }
}
