<?php

namespace App\Services\City;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Who is walking the street on the guest pages: real players, by figure, for
 * the imager to draw. Online players first, capped at eight; when the city is
 * nearly empty, the last few who were around, so the street is never bare.
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

            if ($looks->count() < 3) {
                $looks = $looks->merge(
                    User::query()
                        ->where('online', '0')
                        ->where('rp_system_account', 0)
                        ->orderByDesc('last_online')
                        ->limit(6 - $looks->count())
                        ->pluck('look'),
                );
            }

            return $looks
                ->filter(fn (mixed $look): bool => is_string($look) && $look !== '')
                ->values()
                ->all();
        });

        return $looks;
    }
}
