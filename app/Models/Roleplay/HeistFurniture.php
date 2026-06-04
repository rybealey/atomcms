<?php

namespace App\Models\Roleplay;

use App\Models\Game\Furniture\ItemBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One furniture attached to a heist, with a role describing what it does:
 * keypad (the access gate), search (stand-and-search loot), or pickup
 * (grab-and-go loot). A heist owns many of these; a furni base belongs to
 * exactly one heist (item_base_id is unique).
 *
 * @property int $id
 * @property int $heist_id
 * @property int $item_base_id
 * @property string $role
 * @property-read Heist|null $heist
 * @property-read ItemBase|null $itemBase
 */
class HeistFurniture extends Model
{
    protected $table = 'rp_heist_furnitures';

    protected $guarded = [];

    public const ROLE_KEYPAD = 'keypad';
    public const ROLE_SEARCH = 'search';
    public const ROLE_PICKUP = 'pickup';

    public const ROLE_OPTIONS = [
        self::ROLE_KEYPAD => 'Keypad (access gate)',
        self::ROLE_SEARCH => 'Search (stand and search)',
        self::ROLE_PICKUP => 'Pickup (grab and go)',
    ];

    public function heist(): BelongsTo
    {
        return $this->belongsTo(Heist::class, 'heist_id');
    }

    public function itemBase(): BelongsTo
    {
        return $this->belongsTo(ItemBase::class, 'item_base_id');
    }
}
