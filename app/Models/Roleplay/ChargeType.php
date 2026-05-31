<?php

namespace App\Models\Roleplay;

use Illuminate\Database\Eloquent\Model;

/**
 * A chargeable crime definition consumed by the emulator's :charge command
 * (loaded from rp_charge_types via the plugin's ChargeCatalog). Managed in
 * Roleplay > Charge Types.
 *
 * @property int $id
 * @property string $crime_key
 * @property string $short_key
 * @property string $display_name
 * @property int|null $coin_cost
 * @property int $jail_minutes
 * @property bool $stackable
 * @property bool $is_system
 * @property bool $enabled
 */
class ChargeType extends Model
{
    protected $table = 'rp_charge_types';

    protected $guarded = [];

    protected $casts = [
        'coin_cost' => 'integer',
        'jail_minutes' => 'integer',
        'stackable' => 'boolean',
        'is_system' => 'boolean',
        'enabled' => 'boolean',
    ];
}
