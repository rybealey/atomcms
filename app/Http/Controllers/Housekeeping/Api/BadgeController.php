<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteBadge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $query = WebsiteBadge::query()->orderBy('badge_key');

        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $query->where(function (Builder $where) use ($like): void {
                $where->where('badge_key', 'like', $like)
                    ->orWhere('badge_name', 'like', $like)
                    ->orWhere('badge_description', 'like', $like);
            });
        }

        $page = $query->paginate(60);

        return response()->json([
            'items' => $page->getCollection()->map(fn (WebsiteBadge $badge): array => [
                'code' => $badge->badge_key,
                'name' => (string) $badge->badge_name,
                'description' => (string) $badge->badge_description,
            ])->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }
}
