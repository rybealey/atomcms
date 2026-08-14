<?php

use App\Models\Miscellaneous\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Switches the active theme to 'pixelrp' - the branded copy of 'atom' that
     * carries the pixel-art login landing and registration screens.
     *
     * Only moves installs that are still on a stock theme. Anyone who has
     * deliberately picked something else keeps their choice, and the down()
     * only reverts what this migration actually changed.
     */
    private const PREVIOUS_THEMES = ['atom', 'dusk', '1', ''];

    public function up(): void
    {
        $setting = WebsiteSetting::query()->firstOrCreate(['key' => 'theme'], [
            'value' => 'pixelrp',
            'comment' => 'Specifies the active CMS theme (pixelrp, dusk, atom)',
        ]);

        if (! $setting->wasRecentlyCreated && in_array((string) $setting->value, self::PREVIOUS_THEMES, true)) {
            $setting->update(['value' => 'pixelrp']);
        }
    }

    public function down(): void
    {
        WebsiteSetting::query()
            ->where('key', 'theme')
            ->where('value', 'pixelrp')
            ->update(['value' => 'atom']);
    }
};
