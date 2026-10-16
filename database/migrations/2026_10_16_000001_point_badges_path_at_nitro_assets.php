<?php

use App\Models\Miscellaneous\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The badge album the game draws from is served by the web container at
     * /nitro-assets/assets/c_images/album1584/ (public, no asset pass). The
     * seeded badges_path still named the old Flash client folder, which no
     * longer exists, so every badge on the website was a blank square.
     *
     * Only a setting still on a stock value moves; a hotel that set its own
     * path keeps it. Saving the model clears the settings cache.
     */
    private const STOCK = ['/client/flash/c_images/album1584', '/client/flash/c_images/album1584/', '/gamedata/c_images/album1584/'];

    private const SERVED = '/nitro-assets/assets/c_images/album1584';

    public function up(): void
    {
        $setting = WebsiteSetting::query()->where('key', 'badges_path')->first();

        if ($setting !== null && in_array($setting->value, self::STOCK, true)) {
            $setting->update(['value' => self::SERVED]);
        }
    }

    public function down(): void
    {
        WebsiteSetting::query()->where('key', 'badges_path')->where('value', self::SERVED)->update(['value' => self::STOCK[0]]);
    }
};
