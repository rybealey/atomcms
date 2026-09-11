<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * pixelrp: one row per furni destroyed from the inventory's trash bin.
 *
 * Written by the emulator (RpDeleteInventoryFurniEvent), never by the website.
 * Deletion is permanent, so this is the only record that the item ever existed
 * - which is why `item_name` and `username` are copies taken at the time rather
 * than joins. The item row is gone by the time anyone reads this, and the furni
 * may have been renamed since.
 *
 * `user` is still worth having on top of the stored username: it is what
 * resolves the character back to the account it belongs to.
 *
 * @property int $id
 * @property int $item_id
 * @property int $definition_id
 * @property string $item_name
 * @property int $user_id
 * @property string $username
 * @property Carbon $deleted_at
 * @property-read User|null $user
 *
 * @mixin \Eloquent
 */
class FurniDeleteLog extends Model
{
    protected $table = 'rp_furni_delete_log';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
