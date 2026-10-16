<?php

namespace App\Services\City;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Who is walking the street on the guest pages: players who are in the city
 * right now, by figure, for the imager to draw. Capped at eight; an empty
 * city is an empty street.
 */
class Sidewalk
{
    /** @return array<int, string> */
    public function walkers(): array
    {
        /** @var array<int, string> $looks */
        $looks = Cache::remember('landing.walkers', now()->addSeconds(60), function (): array {
            $looks = User::query()
                ->where('online', '1')
                ->where('rp_system_account', 0)
                ->inRandomOrder()
                ->limit(8)
                ->pluck('look');

            return $looks
                ->filter(fn (mixed $look): bool => is_string($look) && $look !== '')
                ->values()
                ->all();
        });

        return $looks;
    }
}
