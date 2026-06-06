<?php

namespace App\Models\Roleplay;

use App\Models\Game\Furniture\ItemBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    /**
     * Keep items_base.interaction_type in sync so a Search furniture's base is
     * bound to 'rp_heist_search' while it's attached, and reverted to 'default'
     * once it's removed. The emulator still needs a restart to pick up a binding
     * change (interaction_type is read at boot), but the DB stays correct
     * without any manual SQL.
     */
    protected static function booted(): void
    {
        static::saved(function (HeistFurniture $furniture): void {
            $furniture->syncSearchBinding($furniture->item_base_id);
            $previous = $furniture->getOriginal('item_base_id');
            if ($previous && (int) $previous !== (int) $furniture->item_base_id) {
                $furniture->syncSearchBinding((int) $previous);
            }
        });

        static::deleted(function (HeistFurniture $furniture): void {
            $furniture->syncSearchBinding($furniture->item_base_id);
        });
    }

    /**
     * Set the base to 'rp_heist_search' when a role=search furniture references
     * it, or revert it to 'default' when none does. Only ever touches our own
     * binding, never another interaction_type.
     */
    public function syncSearchBinding(?int $baseId): void
    {
        if (! $baseId) {
            return;
        }

        $isSearch = static::query()
            ->where('item_base_id', $baseId)
            ->where('role', self::ROLE_SEARCH)
            ->exists();

        $current = DB::table('items_base')->where('id', $baseId)->value('interaction_type');
        if ($current === null) {
            return; // base not in items_base — nothing to bind
        }

        if ($isSearch && $current !== 'rp_heist_search') {
            DB::table('items_base')->where('id', $baseId)->update(['interaction_type' => 'rp_heist_search']);
        } elseif (! $isSearch && $current === 'rp_heist_search') {
            DB::table('items_base')->where('id', $baseId)->update(['interaction_type' => 'default']);
        }
    }
}
