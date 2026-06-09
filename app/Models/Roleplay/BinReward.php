<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bin_id
 * @property string $reward_type
 * @property string $reward_ref
 * @property int $weight
 * @property int $amount
 * @property-read Bin $bin
 */
class BinReward extends Model
{
    protected $table = 'rp_bin_rewards';

    protected $guarded = [];

    /**
     * Backpack item_keys the emulator's DumpsterDivingManager will
     * actually grant. Mirror of the BACKPACK_ITEM_SPECS map in
     * plugins/pixeltower-rp/.../DumpsterDivingManager.java — when you
     * add a key in one, add it in the other. The emulator grants each
     * with its catalog shape (consumables stack, weapons arrive at full
     * durability, the lockpick stacks as gear), so the staff just pick
     * the item and a weight here.
     */
    public const BACKPACK_ITEM_OPTIONS = [
        'medkit' => 'Medkit',
        'kylie_smoothie' => 'Kylie Jeener Smoothie',
        'chips' => 'Protein Bar',
        'stat_reset' => 'Stat Reset',
        'bat' => 'Baseball Bat',
        'knife' => 'Knife',
        'axe' => 'Axe',
        'lockpick' => 'Lockpick',
    ];

    public const REWARD_TYPE_BACKPACK_ITEM = 'backpack_item';

    public const REWARD_TYPE_ZARA_LTD_TOKEN = 'zara_ltd_token';

    public const REWARD_TYPE_CURRENCY = 'currency';

    /**
     * Currency options for the {@code currency} reward type. The key
     * lands in {@code reward_ref}; the emulator's
     * DumpsterDivingManager maps "coins" to MoneyLedger.credit and
     * "diamonds" to habbo.givePoints(5, amount).
     */
    public const CURRENCY_OPTIONS = [
        'coins' => 'Coins',
        'diamonds' => 'Diamonds',
    ];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
