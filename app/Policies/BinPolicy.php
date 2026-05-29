<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BinPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return hasHousekeepingPermission('manage_bins');
    }

    public function view(User $user)
    {
        return hasHousekeepingPermission('manage_bins');
    }

    public function create(User $user)
    {
        return hasHousekeepingPermission('manage_bins');
    }

    public function update(User $user)
    {
        return hasHousekeepingPermission('manage_bins');
    }

    public function delete(User $user)
    {
        return hasHousekeepingPermission('delete_bins');
    }
}
