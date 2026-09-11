<?php

use App\Models\WebsiteHousekeepingPermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Gates the furni deletion log resource.
     *
     * This has to be a migration rather than only a seeder entry: the deploy
     * runs `artisan migrate` and never runs seeders, and
     * HousekeepingPermissionsService::allows() returns false for a permission
     * it cannot find - so without a row here the page is invisible to
     * everyone, owner included, with nothing on screen to say why.
     *
     * Rank 7 matches manage_commandlogs and the rest of the Logs group.
     *
     * @var array{permission: string, min_rank: int, description: string}
     */
    private array $permission = [
        'permission' => 'manage_furni_delete_logs',
        'min_rank' => 7,
        'description' => 'The minimum rank required before being able to view the furni deletion logs',
    ];

    public function up(): void
    {
        WebsiteHousekeepingPermission::query()->firstOrCreate(
            ['permission' => $this->permission['permission']],
            $this->permission,
        );
    }

    public function down(): void
    {
        WebsiteHousekeepingPermission::query()
            ->where('permission', $this->permission['permission'])
            ->delete();
    }
};
