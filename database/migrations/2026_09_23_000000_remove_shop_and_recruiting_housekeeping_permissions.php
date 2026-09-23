<?php

use App\Models\WebsiteHousekeepingPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The website shop and staff recruiting are gone from the CMS, and the
     * housekeeping permission matrix lists every row in this table - so
     * without this the two would linger there as toggles for nothing.
     *
     * Only the permission rows go. The shop and recruiting tables themselves
     * are left in place: dropping them is a separate, deliberate step.
     *
     * down() restores the seeded defaults, not whatever ranks had been set.
     *
     * @var list<array{permission: string, min_rank: int, description: string}>
     */
    private array $permissions = [
        [
            'permission' => 'manage_shop',
            'min_rank' => 7,
            'description' => 'The minimum rank required before being able to manage shop packages, items and categories',
        ],
        [
            'permission' => 'manage_staff_applications',
            'min_rank' => 7,
            'description' => 'The minimum rank required before being able to manage staff applications',
        ],
    ];

    public function up(): void
    {
        WebsiteHousekeepingPermission::query()
            ->whereIn('permission', array_column($this->permissions, 'permission'))
            ->delete();
    }

    public function down(): void
    {
        foreach ($this->permissions as $permission) {
            WebsiteHousekeepingPermission::query()->firstOrCreate(
                ['permission' => $permission['permission']],
                $permission,
            );
        }
    }
};
