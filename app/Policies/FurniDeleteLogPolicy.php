<?php

namespace App\Policies;

class FurniDeleteLogPolicy extends HousekeepingPolicy
{
    protected function permission(): string
    {
        return 'manage_furni_delete_logs';
    }
}
