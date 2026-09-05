<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * pixelrp: the phone's News app picks a featured image from the article
 * image library shipped with the CMS (public/assets/images/articles - the
 * Habbo promo set). This lists the large promos so the picker can show them;
 * the square *_thumb files are left out. A post stores just the file name and
 * the phone loads /assets/images/articles/<name>.
 */
class NewsImageController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $images = Cache::remember('news-image-library', 3600, function () {
            $dir = public_path('assets/images/articles');
            $files = glob($dir . '/*.{png,gif,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
            $names = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (str_contains(strtolower($name), '_thumb')) {
                    continue;
                }
                $names[] = $name;
            }
            natcasesort($names);

            return array_values($names);
        });

        return response()->json([
            'base' => '/assets/images/articles/',
            'images' => $images,
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
