<?php

namespace App\Policies;

class WebsiteBetaCodePolicy extends HousekeepingPolicy
{
    protected function permission(): string
    {
        return 'manage_website_settings';
    }
}
