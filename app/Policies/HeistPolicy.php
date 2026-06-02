<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HeistPolicy
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

    public function create(User $user)
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
