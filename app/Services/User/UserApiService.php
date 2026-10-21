<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class UserApiService
{
    public function fetchUser(string $username): ?User
    {
        return User::query()
            ->select(['username', 'motto', 'look'])
            ->where('username', $username)
            // pixelrp: the hotel's own accounts (PixelRP) are never looked up.
            ->where('rp_system_account', 0)
            ->first();
    }

    /**
     * @param  array<int, string>  $columns
     *
     * @return Collection<int, User>
     */
    public function onlineUsers(array $columns = ['username', 'motto', 'look'], int $limit = 50): Collection
    {
        $cacheKey = sprintf('api_online_users:%s:%d', implode(',', $columns), $limit);

        return Cache::remember($cacheKey, now()->addSeconds(30), fn () => User::query()
            ->select($columns)
            ->where('online', '1')
            ->inRandomOrder()
            ->limit($limit)
            ->get());
    }

    // pixelrp: every open game client asks for this (OnlineCountView), and
    // nginx answers them all from one count for 10 seconds (docker/web/nginx.conf,
    // /api/online-count) - so it counts fresh, at most once in those 10 seconds
    // whoever is asking, rather than reading the website's 30-second cache on
    // top of that and showing the hotel's count up to a minute late.
    public function onlineUserCount(): int
    {
        return User::where('online', '1')->count();
    }
}
