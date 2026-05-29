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
     * add a key in one, add it in the other.
     */
    public const BACKPACK_ITEM_OPTIONS = [
        'medkit' => 'Medkit',
        'kylie_smoothie' => 'Kylie Jeener Smoothie',
        'chips' => 'Protein Bar',
    ];

    public const REWARD_TYPE_BACKPACK_ITEM = 'backpack_item';

    public const REWARD_TYPE_ZARA_LTD_TOKEN = 'zara_ltd_token';

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
