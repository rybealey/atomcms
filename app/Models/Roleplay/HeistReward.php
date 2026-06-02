<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foundation clone of {@link BinReward}.
 *
 * @property int $id
 * @property int $heist_id
 * @property string $reward_type
 * @property string $reward_ref
 * @property int $weight
 * @property int $amount
 * @property-read Heist $heist
 */
class HeistReward extends Model
{
    protected $table = 'rp_heist_rewards';

    protected $guarded = [];

    /**
     * Backpack item_keys the emulator's HeistManager will actually grant.
     * Mirror of the BACKPACK_ITEM_SPECS map in
     * plugins/pixeltower-rp/.../heist/HeistManager.java — when you add a
     * key in one, add it in the other.
     */
    public const BACKPACK_ITEM_OPTIONS = [
        'medkit' => 'Medkit',
        'kylie_smoothie' => 'Kylie Jeener Smoothie',
        'chips' => 'Protein Bar',
    ];

    public const REWARD_TYPE_BACKPACK_ITEM = 'backpack_item';

    public const REWARD_TYPE_ZARA_LTD_TOKEN = 'zara_ltd_token';

    public const REWARD_TYPE_CURRENCY = 'currency';

    /**
     * Currency options for the {@code currency} reward type. The key lands
     * in {@code reward_ref}; the emulator's HeistManager maps "coins" to
     * MoneyLedger.credit and "diamonds" to habbo.givePoints(5, amount).
     */
    public const CURRENCY_OPTIONS = [
        'coins' => 'Coins',
        'diamonds' => 'Diamonds',
    ];

    public function heist(): BelongsTo
    {
        return $this->belongsTo(Heist::class, 'heist_id');
    }
}
