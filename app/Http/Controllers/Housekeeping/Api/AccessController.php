<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\HotelResult;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Access: both permission systems in one matrix. Panel screens and actions
 * are website_housekeeping_permissions.min_rank; in-game commands are
 * permissions_commands.group_id (the lowest rank that may use them). A web
 * action that mirrors a command checks the command's row, so this is where
 * "who can do what" changes for both at once.
 */
class AccessController extends Controller
{
    /**
     * What each housekeeping capability opens, for the matrix. `group` sorts it:
     * screens, actions, or capabilities nothing in the panel reads any more.
     *
     * @var array<string, array{label: string, group: string, mirror?: string}>
     */
    private const SCREENS = [
        'can_access_housekeeping' => ['label' => 'Open housekeeping', 'group' => 'screens'],
        'manage_bans' => ['label' => 'Discipline: bans, mutes and notes', 'group' => 'screens', 'mirror' => 'command_ban'],
        'edit_user' => ['label' => 'Security: IPs and devices', 'group' => 'screens'],
        'manage_support' => ['label' => 'Support queue and tickets', 'group' => 'screens'],
        'manage_room_chatlogs' => ['label' => 'Room chat logs', 'group' => 'screens'],
        'manage_private_chatlogs' => ['label' => 'Private chat logs', 'group' => 'screens'],
        'moderate_sitch' => ['label' => 'Sitch moderation', 'group' => 'screens', 'mirror' => 'rp_sitch_moderate'],
        'manage_camera_web' => ['label' => 'Photos', 'group' => 'screens'],
        'view_economy' => ['label' => 'Economy and ledgers', 'group' => 'screens', 'mirror' => 'rp_city_balance'],
        'view_payments' => ['label' => 'Payment data', 'group' => 'screens'],
        'manage_badges' => ['label' => 'Badges and approvals', 'group' => 'screens'],
        'manage_catalog_pages' => ['label' => 'Catalog and furni', 'group' => 'screens', 'mirror' => 'command_update_catalog'],
        'manage_achievements' => ['label' => 'Achievements', 'group' => 'screens'],
        'manage_emulator_texts' => ['label' => 'Emulator texts', 'group' => 'screens'],
        'manage_wordfilter' => ['label' => 'Word filter', 'group' => 'screens', 'mirror' => 'command_update_filter'],
        'edit_article' => ['label' => 'Phone news', 'group' => 'screens'],
        'manage_teams' => ['label' => 'Website: teams', 'group' => 'screens'],
        'manage_website_ads' => ['label' => 'Website: ads', 'group' => 'screens'],
        'manage_home_items' => ['label' => 'Website: home items', 'group' => 'screens'],
        'manage_article_tags' => ['label' => 'Website: tags', 'group' => 'screens'],
        'view_activity_logs' => ['label' => 'Logs and staff audit', 'group' => 'screens'],
        'manage_commandlogs' => ['label' => 'Logs: staff tool commands', 'group' => 'screens'],
        'manage_furni_delete_logs' => ['label' => 'Logs: furni deletes', 'group' => 'screens'],
        'manage_housekeeping_permissions' => ['label' => 'Access and staff ranks', 'group' => 'screens'],
        'manage_website_settings' => ['label' => 'Website settings', 'group' => 'screens'],
        'manage_emulator_settings' => ['label' => 'Emulator settings', 'group' => 'screens', 'mirror' => 'rp_city_hotel'],
    ];

    public function __construct(private readonly RankDirectory $ranks) {}

    public function index(): JsonResponse
    {
        $rows = WebsiteHousekeepingPermission::query()->orderBy('permission')->get();
        $screens = [];
        $unused = [];

        foreach ($rows as $row) {
            $meta = self::SCREENS[(string) $row->permission] ?? null;
            $entry = [
                'key' => (string) $row->permission,
                'label' => $meta['label'] ?? str_replace('_', ' ', ucfirst((string) $row->permission)),
                'mirror' => $meta['mirror'] ?? null,
                'min_rank' => (int) $row->min_rank,
                'description' => (string) $row->description,
            ];

            if ($meta !== null) {
                $screens[array_search((string) $row->permission, array_keys(self::SCREENS), true)] = $entry;
            } else {
                $unused[] = $entry;
            }
        }

        ksort($screens);
        $thresholds = GameAbilities::thresholds();
        $mirrors = [];

        foreach (self::SCREENS as $key => $meta) {
            if (isset($meta['mirror'])) {
                $mirrors[$meta['mirror']] = $meta['label'];
            }
        }

        $commands = [];

        foreach (GameAbilities::MIRRORED as $command => $label) {
            if (isset($thresholds[$command])) {
                $commands[] = ['key' => $command, 'label' => $command, 'mirror' => $label, 'min_rank' => $thresholds[$command], 'screen' => $mirrors[$command] ?? null];
            }
        }

        $staffRank = (int) setting('min_staff_rank', 4);
        $staff = User::query()->where('rank', '>=', $staffRank)->orderByDesc('rank')->orderBy('username')->limit(30)->get(['id', 'username', 'look', 'rank', 'online', 'team_id']);
        $teams = RoleplaySchema::has('website_teams') ? DB::table('website_teams')->pluck('rank_name', 'id') : collect();
        $changes = Activity::query()
            ->whereIn('log_name', [Audit::LOG, 'housekeeping-permissions'])
            ->whereIn('event', ['access', 'updated', 'rank'])
            ->latest('id')
            ->limit(8)
            ->get();
        $causers = PlayerRefs::many($changes->pluck('causer_id'));

        return response()->json([
            'ranks' => $this->ranks->all()->values(),
            'screens' => array_values($screens),
            'unused' => $unused,
            'commands' => $commands,
            'staff' => $staff->map(fn (User $u): array => [
                'player' => ['id' => (int) $u->id, 'username' => (string) $u->username, 'look' => (string) $u->look],
                'rank' => (int) $u->rank,
                'online' => (string) $u->online === '1',
                'team' => $teams->get($u->team_id),
            ])->values(),
            'changes' => $changes->map(fn (Activity $a): array => [
                'id' => (int) $a->id,
                'at' => Epoch::of($a->created_at),
                'actor' => PlayerRefs::pick($causers, $a->causer_id),
                'summary' => lcfirst((string) $a->description),
                'reason' => $a->properties?->get('reason'),
            ])->values(),
        ]);
    }

    /**
     * Save a batch of threshold changes from the matrix, with one reason.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screens' => ['nullable', 'array'],
            'screens.*' => ['integer', 'min:1', 'max:255'],
            'commands' => ['nullable', 'array'],
            'commands.*' => ['integer', 'min:1', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $top = (int) $this->ranks->all()->max('id');
        $mine = (int) $actor->rank;
        $changed = 0;

        foreach ($data['screens'] ?? [] as $key => $rank) {
            $row = WebsiteHousekeepingPermission::query()->where('permission', (string) $key)->first();

            if (! $row instanceof WebsiteHousekeepingPermission) {
                continue;
            }

            $rank = min((int) $rank, $top);
            $old = (int) $row->min_rank;

            if ($old === $rank) {
                continue;
            }

            // Nobody moves a capability past their own rank, in either direction.
            abort_if($old > $mine || $rank > $mine, 403, sprintf('%s is above your rank.', $key));
            abort_if(in_array($key, ['can_access_housekeeping', 'manage_housekeeping_permissions'], true) && $rank > $mine, 422, 'That would lock you out of this screen.');

            $row->min_rank = $rank;
            $row->save();
            Audit::record('access', sprintf('%s %s to rank %d', $rank < $old ? 'opened' : 'raised', self::SCREENS[$key]['label'] ?? $key, $rank), before: ['min_rank' => $old], after: ['min_rank' => $rank], reason: $data['reason'], target: ['type' => 'housekeeping_permission', 'id' => (string) $key]);
            $changed++;
        }

        HousekeepingPermissionsService::clearCache();
        $reload = null;
        $thresholds = GameAbilities::thresholds();

        foreach ($data['commands'] ?? [] as $command => $rank) {
            $command = (string) $command;

            if (! array_key_exists($command, GameAbilities::MIRRORED) || ! isset($thresholds[$command])) {
                continue;
            }

            $rank = min((int) $rank, $top);
            $old = $thresholds[$command];

            if ($old === $rank) {
                continue;
            }

            abort_if($old > $mine || $rank > $mine, 403, sprintf('%s is above your rank.', $command));
            DB::table('permissions_commands')->where('command', $command)->update(['group_id' => $rank]);
            Audit::record('access', sprintf('%s %s to rank %d', $rank < $old ? 'opened' : 'raised', $command, $rank), before: ['group_id' => $old], after: ['group_id' => $rank], reason: $data['reason'], target: ['type' => 'command', 'id' => $command], path: 'saved');
            $changed++;
            $reload = true;
        }

        $result = null;

        if ($reload) {
            GameAbilities::clearCache();
            $result = Hotel::reload('ranks');
        }

        return response()->json([
            'changed' => $changed,
            'reloaded' => $result instanceof HotelResult ? $result->sent : null,
            'message' => $changed === 0 ? 'Nothing changed' : sprintf('%d %s saved%s', $changed, $changed === 1 ? 'change' : 'changes', $result instanceof HotelResult ? ($result->sent ? '; the hotel reloaded ranks' : '; the hotel could not be reached to reload ranks') : ''),
        ]);
    }
}
