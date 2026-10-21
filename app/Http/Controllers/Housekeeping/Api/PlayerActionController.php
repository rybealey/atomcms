<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Emulator\Contracts\BadgeRepository;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Account;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\GameAbilities;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\HotelResult;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\Pulse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What staff can do to a player from Player 360. Live actions go through
 * RCON to an online player; the same action for an offline player writes the
 * database the emulator reads at their next login. Every one needs a reason
 * and lands in the audit trail.
 */
class PlayerActionController extends Controller
{
    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    public function alert(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:500']]);
        $player = $this->player($id);
        $this->ability($request, 'command_alert_user');
        abort_unless((string) $player->online === '1', 422, sprintf('%s is offline. An alert only reaches a player who is online.', $player->username));

        $result = Hotel::alert($id, (string) $data['message']);
        Audit::record('alert', sprintf('alerted %s', $player->username), $id, after: ['message' => $data['message']], rcon: $result);

        return $this->done($result, sprintf('Alert sent to %s', $player->username));
    }

    /**
     * Alert several players at once from the directory, or the whole hotel
     * from Today: one alert_user per online player, since PlusEMU's RCON has
     * no hotel-wide alert.
     */
    public function bulkAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:500'],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'everyone' => ['nullable', 'boolean'],
        ]);
        $this->ability($request, ! empty($data['everyone']) ? 'command_hotel_alert' : 'command_alert_user');

        $query = DB::table('users')->where('online', '1');

        if (empty($data['everyone'])) {
            $query->whereIn('id', $data['ids'] ?? []);
        }

        $ids = $query->limit(1000)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $sent = 0;

        foreach ($ids as $userId) {
            if (Hotel::alert($userId, (string) $data['message'])->sent) {
                $sent++;
            }
        }

        $summary = ! empty($data['everyone']) ? sprintf('sent a hotel alert to %d players', $sent) : sprintf('alerted %d players', $sent);
        Audit::record(! empty($data['everyone']) ? 'hotel_alert' : 'bulk_alert', $summary, after: ['message' => $data['message'], 'players' => $ids], rcon: new HotelResult('alertuser', $sent > 0, sprintf('%d of %d sent', $sent, count($ids))));

        return response()->json(['ok' => $sent > 0 || $ids === [], 'sent' => $sent, 'online' => count($ids), 'message' => ucfirst($summary)]);
    }

    public function disconnect(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $player = $this->player($id);
        $this->ability($request, 'command_disconnect');
        $this->outranks($request, $player);
        abort_unless((string) $player->online === '1', 422, sprintf('%s is not online.', $player->username));

        $result = Hotel::disconnect($id);
        Audit::record('disconnect', sprintf('disconnected %s', $player->username), $id, reason: $data['reason'], rcon: $result);

        return $this->done($result, sprintf('%s was disconnected', $player->username));
    }

    /**
     * Give or take coins. Online: RCON, and the emulator records it in the
     * coin ledger. Offline: the users row, with a ledger line of our own.
     */
    public function coins(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $player = $this->player($id);
        $this->ability($request, 'rp_city_balance');
        $amount = (int) $data['amount'];
        $before = (int) $player->credits;
        abort_if($amount < 0 && $before + $amount < 0, 422, sprintf('%s only has %d coins.', $player->username, $before));

        if ((string) $player->online === '1') {
            $result = Hotel::adjustCoins($id, $amount);
            Audit::record('coins', sprintf('%s %d coins %s %s', $amount > 0 ? 'gave' : 'took', abs($amount), $amount > 0 ? 'to' : 'from', $player->username), $id, ['coins' => $before], ['coins' => $before + $amount], $data['reason'], $result);

            return $this->done($result, sprintf('%s %s %d coins', $player->username, $amount > 0 ? 'got' : 'lost', abs($amount)));
        }

        DB::transaction(function () use ($id, $amount, $player, $data): void {
            DB::table('users')->where('id', $id)->increment('credits', $amount);

            if (RoleplaySchema::has('rp_coin_ledger')) {
                DB::table('rp_coin_ledger')->insert([
                    'user_id' => $id,
                    'username' => $player->username,
                    'amount' => $amount,
                    'balance_after' => (int) DB::table('users')->where('id', $id)->value('credits'),
                    'source' => mb_substr(($amount > 0 ? 'Given by staff' : 'Taken by staff') . ' · ' . $data['reason'], 0, 96),
                    'created_at' => time(),
                ]);
            }
        });
        Audit::record('coins', sprintf('%s %d coins %s %s', $amount > 0 ? 'gave' : 'took', abs($amount), $amount > 0 ? 'to' : 'from', $player->username), $id, ['coins' => $before], ['coins' => $before + $amount], $data['reason']);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => sprintf('%s will have %d coins at next login', $player->username, $before + $amount)]);
    }

    public function giveBadge(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'badge' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $player = $this->player($id);
        $this->ability($request, 'command_give_badge');
        $badge = (string) $data['badge'];

        if ((string) $player->online === '1') {
            $result = Hotel::giveBadge($id, $badge);
            Audit::record('badge', sprintf('gave the %s badge to %s', $badge, $player->username), $id, after: ['badge' => $badge], reason: $data['reason'], rcon: $result);

            return $this->done($result, sprintf('%s got the %s badge', $player->username, $badge));
        }

        app(BadgeRepository::class)->grant($player, $badge);
        Audit::record('badge', sprintf('gave the %s badge to %s', $badge, $player->username), $id, after: ['badge' => $badge], reason: $data['reason']);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => sprintf('%s will see the %s badge at next login', $player->username, $badge)]);
    }

    public function takeBadge(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'badge' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $player = $this->player($id);
        $this->ability($request, 'command_give_badge');
        $badge = (string) $data['badge'];

        if ((string) $player->online === '1') {
            $result = Hotel::takeBadge($id, $badge);
            Audit::record('badge', sprintf('took the %s badge from %s', $badge, $player->username), $id, before: ['badge' => $badge], reason: $data['reason'], rcon: $result);

            return $this->done($result, sprintf('Took the %s badge from %s', $badge, $player->username));
        }

        app(BadgeRepository::class)->revoke($player, $badge);
        Audit::record('badge', sprintf('took the %s badge from %s', $badge, $player->username), $id, before: ['badge' => $badge], reason: $data['reason']);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => sprintf('Took the %s badge from %s', $badge, $player->username)]);
    }

    /**
     * Ban the whole account: one row per character (the emulator checks the
     * name it logs in with), optionally the IP and machine too. The emulator
     * re-reads bans and every online character is disconnected.
     */
    public function ban(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:60', 'max:315576000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'ip' => ['nullable', 'boolean'],
            'machine' => ['nullable', 'boolean'],
        ]);
        $player = $this->player($id);
        $this->permission($request, 'manage_bans');
        $this->outranks($request, $player);
        abort_unless(PlayerColumns::isPlus(), 422, 'Bans from here need the PlusEMU bans table.');
        /** @var User $actor */
        $actor = $request->user();
        $rootId = Account::rootId($player);
        $characters = Account::characters($rootId);
        $now = time();
        $expire = $now + (int) $data['seconds'];
        $rows = [];

        foreach ($characters as $character) {
            $rows[] = ['bantype' => 'user', 'value' => (string) $character->username, 'reason' => $data['reason'], 'expire' => $expire, 'added_by' => $actor->username, 'added_date' => (string) $now];
        }

        $canSeeIps = $this->permissions->allows($actor, 'edit_user');

        if (! empty($data['ip']) && $canSeeIps) {
            $ip = (string) DB::table('users')->where('id', $id)->value(PlayerColumns::currentIp());

            if ($ip !== '') {
                $rows[] = ['bantype' => 'ip', 'value' => $ip, 'reason' => $data['reason'], 'expire' => $expire, 'added_by' => $actor->username, 'added_date' => (string) $now];
            }
        }

        if (! empty($data['machine']) && $canSeeIps && RoleplaySchema::hasColumn('users', 'machine_id')) {
            $machine = (string) DB::table('users')->where('id', $id)->value('machine_id');

            if ($machine !== '') {
                $rows[] = ['bantype' => 'machine', 'value' => $machine, 'reason' => $data['reason'], 'expire' => $expire, 'added_by' => $actor->username, 'added_date' => (string) $now];
            }
        }

        DB::table('bans')->insert($rows);
        $reload = Hotel::reload('bans');

        foreach ($characters as $character) {
            if ((string) $character->online === '1') {
                Hotel::disconnect((int) $character->id);
            }
        }

        Audit::record('ban', sprintf('banned %s for %s', $player->username, self::length((int) $data['seconds'])), $id, after: ['until' => $expire, 'characters' => $characters->pluck('username')->all(), 'ip' => ! empty($data['ip']), 'machine' => ! empty($data['machine'])], reason: $data['reason'], rcon: $reload);
        Pulse::forget();

        return response()->json(['ok' => true, 'path' => 'saved', 'reloaded' => $reload->sent, 'message' => sprintf('Account banned for %s', self::length((int) $data['seconds']))]);
    }

    /**
     * Lift every active ban on the account early. The rows stay as history.
     */
    public function unban(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $player = $this->player($id);
        $this->permission($request, 'manage_bans');
        $names = Account::usernames(Account::rootId($player));
        $lifted = DB::table('bans')->where('bantype', 'user')->whereIn('value', $names)->where('expire', '>', time())->update(['expire' => time()]);
        $reload = Hotel::reload('bans');
        Audit::record('unban', sprintf('lifted the ban on %s', $player->username), $id, after: ['lifted' => $lifted], reason: $data['reason'], rcon: $reload);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => $lifted > 0 ? 'Ban lifted' : 'There was no active ban']);
    }

    /**
     * Mute a character. The emulator keeps the time left in memory while they
     * are online and writes it at logout, so only an offline character can be
     * muted from here.
     */
    public function mute(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:31536000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $player = $this->player($id);
        $this->ability($request, 'command_mute');
        $this->outranks($request, $player);
        abort_if((string) $player->online === '1', 422, sprintf('%s is online. Mute them in game with :mute - a mute written here would be lost when they log out.', $player->username));
        $before = (float) $player->getAttribute('time_muted');
        DB::table('users')->where('id', $id)->update(['time_muted' => (int) $data['seconds']]);
        $seconds = (int) $data['seconds'];
        Audit::record('mute', $seconds > 0 ? sprintf('muted %s for %s', $player->username, self::length($seconds)) : sprintf('unmuted %s', $player->username), $id, ['seconds' => (int) $before], ['seconds' => $seconds], $data['reason']);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => $seconds > 0 ? sprintf('%s is muted for %s from their next login', $player->username, self::length($seconds)) : sprintf('%s is no longer muted', $player->username)]);
    }

    public function note(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'pinned' => ['nullable', 'boolean'],
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
        ]);
        $this->permission($request, 'manage_bans');
        /** @var User $actor */
        $actor = $request->user();
        $targets = array_values(array_unique(array_merge([$id], array_map('intval', $data['ids'] ?? []))));
        $made = 0;

        foreach ($targets as $target) {
            $player = User::query()->find($target);

            if (! $player instanceof User) {
                continue;
            }

            DB::table('website_staff_notes')->insert([
                'account_id' => Account::rootId($player),
                'character_id' => $player->id,
                'author_id' => $actor->id,
                'body' => $data['body'],
                'pinned' => (bool) ($data['pinned'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Audit::record('note', sprintf('added a staff note on %s', $player->username), (int) $player->id, after: ['note' => $data['body']]);
            $made++;
        }

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => $made === 1 ? 'Note saved' : sprintf('Note saved on %d players', $made)]);
    }

    public function updateNote(Request $request, int $id, int $note): JsonResponse
    {
        $data = $request->validate(['pinned' => ['nullable', 'boolean'], 'delete' => ['nullable', 'boolean']]);
        $this->permission($request, 'manage_bans');
        $player = $this->player($id);
        $row = DB::table('website_staff_notes')->where('id', $note)->where('account_id', Account::rootId($player))->first();
        abort_if($row === null, 404);

        if (! empty($data['delete'])) {
            DB::table('website_staff_notes')->where('id', $note)->delete();
            Audit::record('note', sprintf('deleted a staff note on %s', $player->username), $id, before: ['note' => $row->body]);

            return response()->json(['ok' => true, 'path' => 'saved', 'message' => 'Note deleted']);
        }

        DB::table('website_staff_notes')->where('id', $note)->update(['pinned' => (bool) ($data['pinned'] ?? false), 'updated_at' => now()]);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => ! empty($data['pinned']) ? 'Note pinned' : 'Note unpinned']);
    }

    /**
     * Mark two linked accounts as a household, dismiss the link, or flag it
     * again. Both directions, so each account's page agrees.
     */
    public function reviewLink(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'other' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:household,dismissed,flagged'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->permission($request, 'edit_user');
        /** @var User $actor */
        $actor = $request->user();
        $player = $this->player($id);
        $a = Account::rootId($player);
        $b = Account::rootId((int) $data['other']);

        foreach ([[$a, $b], [$b, $a]] as [$one, $two]) {
            if ($data['status'] === 'flagged') {
                DB::table('website_housekeeping_link_reviews')->where('account_id', $one)->where('other_account_id', $two)->delete();

                continue;
            }

            DB::table('website_housekeeping_link_reviews')->updateOrInsert(
                ['account_id' => $one, 'other_account_id' => $two],
                ['status' => $data['status'], 'reviewed_by' => $actor->id, 'note' => $data['note'] ?? null, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $other = User::query()->find((int) $data['other']);
        Audit::record('link_review', sprintf('marked %s and %s as %s', $player->username, $other instanceof User ? $other->username : '#' . $data['other'], $data['status'] === 'flagged' ? 'flagged' : ($data['status'] === 'household' ? 'a household' : 'not linked')), $id, after: ['other' => (int) $data['other'], 'status' => $data['status']]);

        return response()->json(['ok' => true, 'path' => 'saved', 'message' => match ($data['status']) {
            'household' => 'Marked as a household', 'dismissed' => 'Link dismissed', default => 'Flagged again'
        }]);
    }

    public static function length(int $seconds): string
    {
        return match (true) {
            $seconds >= 31536000 && $seconds % 31536000 === 0 => sprintf('%d %s', $seconds / 31536000, $seconds === 31536000 ? 'year' : 'years'),
            $seconds >= 86400 && $seconds % 86400 === 0 => sprintf('%d %s', $seconds / 86400, $seconds === 86400 ? 'day' : 'days'),
            $seconds >= 3600 && $seconds % 3600 === 0 => sprintf('%d %s', $seconds / 3600, $seconds === 3600 ? 'hour' : 'hours'),
            default => sprintf('%d %s', intdiv($seconds, 60), intdiv($seconds, 60) === 1 ? 'minute' : 'minutes'),
        };
    }

    private function player(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    private function ability(Request $request, string $ability): void
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless(GameAbilities::allows($actor, $ability), 403, sprintf('Your rank cannot do this in game either (%s).', $ability));
    }

    private function permission(Request $request, string $permission): void
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($this->permissions->allows($actor, $permission), 403, sprintf('This needs the %s capability.', $permission));
    }

    private function outranks(Request $request, User $player): void
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless((int) $player->rank < (int) $actor->rank, 403, sprintf('%s is your rank or above.', $player->username));
    }

    private function done(HotelResult $result, string $message): JsonResponse
    {
        return response()->json([
            'ok' => $result->sent,
            'path' => 'live',
            'message' => $result->sent ? $message : 'The emulator could not be reached: ' . $result->message,
        ], $result->sent ? 200 : 502);
    }
}
