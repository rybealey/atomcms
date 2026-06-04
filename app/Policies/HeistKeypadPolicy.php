<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization for the "Roleplay > Heist Keypads" manager. Reuses the same
 * housekeeping permissions as the Heists resource so the two roleplay tools
 * stay rank-aligned. Create is intentionally absent — keypad rows are
 * created by gameplay (the emulator), not by staff.
 */
class HeistKeypadPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return hasHousekeepingPermission('manage_heists');
    }

    public function view(User $user)
    {
        return hasHousekeepingPermission('manage_heists');
    }

    public function update(User $user)
    {
        return hasHousekeepingPermission('manage_heists');
    }

    public function delete(User $user)
    {
        return hasHousekeepingPermission('delete_heists');
    }
}
