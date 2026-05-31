<?php

namespace App\Policies;

use App\Models\Roleplay\ChargeType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChargeTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return hasHousekeepingPermission('manage_charge_types');
    }

    public function view(User $user)
    {
        return hasHousekeepingPermission('manage_charge_types');
    }

    public function create(User $user)
    {
        return hasHousekeepingPermission('manage_charge_types');
    }

    public function update(User $user, ChargeType $chargeType)
    {
        // System crimes (e.g. 911abuse) are referenced by name in plugin code.
        // Their tunable fields stay editable via the form, but the crime_key
        // field is disabled there — the row itself remains updatable so cost /
        // jail / enabled can still be changed.
        return hasHousekeepingPermission('manage_charge_types');
    }

    public function delete(User $user, ChargeType $chargeType)
    {
        // Never let staff delete a crime the plugin depends on.
        if ($chargeType->is_system) {
            return false;
        }

        return hasHousekeepingPermission('delete_charge_types');
    }
}
