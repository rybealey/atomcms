<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteHousekeepingPermission;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The rank ladder and the housekeeping capability matrix: each capability is
 * granted from a minimum rank upward, and moving that threshold is the only
 * write here.
 */
class StaffController extends Controller
{
    public function __construct(private readonly RankDirectory $ranks) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'ranks' => $this->ranks->all()->values(),
            'permissions' => $this->permissions(),
        ]);
    }

    public function updatePermission(Request $request, string $permission): JsonResponse
    {
        $validated = $request->validate([
            'min_rank' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $row = WebsiteHousekeepingPermission::query()->where('permission', $permission)->firstOrFail();
        $old = (int) $row->min_rank;
        $new = (int) $validated['min_rank'];

        if ($old !== $new) {
            $row->min_rank = $new;
            $row->save();

            activity('housekeeping-permissions')
                ->causedBy($actor)
                ->performedOn($row)
                ->withProperties(['permission' => $permission, 'old' => $old, 'new' => $new])
                ->event('updated')
                ->log("Capability {$permission} now starts at rank {$new}");
        }

        return response()->json(['permissions' => $this->permissions()]);
    }

    /**
     * @return list<array{permission: string, min_rank: int, description: string}>
     */
    private function permissions(): array
    {
        return WebsiteHousekeepingPermission::query()
            ->orderBy('permission')
            ->get()
            ->map(fn (WebsiteHousekeepingPermission $row): array => [
                'permission' => $row->permission,
                'min_rank' => (int) $row->min_rank,
                'description' => (string) $row->description,
            ])
            ->values()
            ->all();
    }
}
