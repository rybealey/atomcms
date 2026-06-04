<?php

namespace App\Models\Roleplay;

use App\Models\Game\Furniture\ItemBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One furniture attached to a heist, with a role describing what it does:
 *   - keypad: the access gate — keyed by a specific PLACED furni
 *     ({@code placed_item_id}) and carrying that keypad's current access code
 *     ({@code next_key}, re-rolled per open) and {@code room_id}.
 *   - search / pickup: loot — keyed by furniture TYPE ({@code item_base_id}).
 *
 * A heist owns many of these. A loot furni base belongs to exactly one heist
 * ({@code item_base_id} unique); a placed keypad is unique
 * ({@code placed_item_id} unique). The unused id column for the row's role
 * stays null.
 *
 * @property int $id
 * @property int $heist_id
 * @property int|null $item_base_id
 * @property int|null $placed_item_id
 * @property int|null $room_id
 * @property int|null $next_key
 * @property int|null $search_duration_seconds
 * @property string $role
 * @property-read Heist|null $heist
 * @property-read ItemBase|null $itemBase
 */
class HeistFurniture extends Model
{
    protected $table = 'rp_heist_furnitures';

    protected $guarded = [];

    public const ROLE_KEYPAD = 'keypad';
    public const ROLE_ENTRANCE = 'entrance';
    public const ROLE_EXIT = 'exit';
    public const ROLE_SEARCH = 'search';
    public const ROLE_PICKUP = 'pickup';

    public const ROLE_OPTIONS = [
        self::ROLE_KEYPAD => 'Keypad (access gate)',
        self::ROLE_ENTRANCE => 'Entrance teleporter',
        self::ROLE_EXIT => 'Exit teleporter',
        self::ROLE_SEARCH => 'Search (stand and search)',
        self::ROLE_PICKUP => 'Pickup (grab and go)',
    ];

    /** Roles keyed by a specific placed furni (placed_item_id), not a base. */
    public const PLACEMENT_ROLES = [
        self::ROLE_KEYPAD,
        self::ROLE_ENTRANCE,
        self::ROLE_EXIT,
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
