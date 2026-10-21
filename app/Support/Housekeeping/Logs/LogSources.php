<?php

namespace App\Support\Housekeeping\Logs;

use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Every log table the hotel writes, as one list of adapters. A row reads as
 * `parts`: a sentence of plain text, player chips, a room chip, a coin amount
 * and a quote, so the explorer and Player 360's timeline draw the same row
 * the same way (the timeline drops the leading chip when it is the player
 * whose page it is).
 */
final class LogSources
{
    public const GROUPS = [
        'chat' => 'Chat',
        'staff' => 'Staff',
        'money' => 'Money and trades',
        'justice' => 'Justice',
        'furni' => 'Furni and catalog',
        'accounts' => 'Accounts',
    ];

    public const KINDS = [
        'chat' => 'Chat',
        'money' => 'Money',
        'trade' => 'Trades',
        'justice' => 'Justice',
        'login' => 'Logins',
        'guard' => 'Security',
        'furni' => 'Furni',
        'staff' => 'Staff actions',
    ];

    /** @var array<string, LogSource>|null */
    private static ?array $all = null;

    /**
     * The sources whose tables exist, keyed by table name, in a fixed order
     * (the order is part of the paging cursor).
     *
     * @return array<string, LogSource>
     */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $sources = [];

        foreach (self::definitions() as $source) {
            if (RoleplaySchema::has($source->table())) {
                $sources[$source->key] = $source;
            }
        }

        return self::$all = $sources;
    }

    /**
     * The sources this staff member may read.
     *
     * @return array<string, LogSource>
     */
    public static function readableBy(User $user): array
    {
        $permissions = app(HousekeepingPermissionsService::class);

        return array_filter(self::all(), fn (LogSource $source): bool => $source->permission === null || $permissions->allows($user, $source->permission));
    }

    public static function forget(): void
    {
        self::$all = null;
    }

    /**
     * @return array{player: int, name?: string}
     */
    private static function p(mixed $id, ?string $name = null): array
    {
        return $name !== null && $name !== '' ? ['player' => (int) $id, 'name' => $name] : ['player' => (int) $id];
    }

    /**
     * @param  array<int, string>  $rooms
     *
     * @return array{room: int, name: string}
     */
    private static function r(mixed $id, ?string $name, array $rooms): array
    {
        $id = (int) $id;

        return ['room' => $id, 'name' => $name !== null && $name !== '' ? $name : ($rooms[$id] ?? 'Room ' . $id)];
    }

    /**
     * "medkit:2,snack:1" → "2 medkits, 1 snack" for backpack trades.
     */
    public static function backpack(string $gave): string
    {
        if (trim($gave) === '') {
            return 'nothing';
        }

        $parts = [];

        foreach (explode(',', $gave) as $piece) {
            [$item, $count] = array_pad(explode(':', $piece, 2), 2, '1');
            $item = str_replace('_', ' ', trim($item));
            $count = (int) $count;

            if ($item === '') {
                continue;
            }

            $parts[] = $count > 1 ? sprintf('%d× %s', $count, $item) : $item;
        }

        return $parts === [] ? 'nothing' : implode(', ', $parts);
    }

    /**
     * @return array<int, string> crime id → name, for charge rows.
     */
    public static function crimeNames(): array
    {
        if (! RoleplaySchema::has('rp_crimes')) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Cache::remember('housekeeping:crime-names', now()->addMinutes(5), fn (): array => DB::table('rp_crimes')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all());

        return $names;
    }

    public static function guardReason(int $reason): string
    {
        return match ($reason) {
            1 => 'DevTools shortcut',
            2 => 'DevTools opened',
            3 => 'Automated browser',
            default => 'Reason ' . $reason,
        };
    }

    /**
     * "Mozilla/5.0 (Macintosh…) Chrome/…" → "Chrome on macOS".
     */
    public static function browser(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'a browser',
        };
        $os = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return $os !== '' ? $browser . ' on ' . $os : $browser;
    }

    /**
     * @return list<LogSource>
     */
    private static function definitions(): array
    {
        return [
            new LogSource(
                key: 'chatlogs', group: 'chat', kind: 'chat', permission: 'manage_room_chatlogs',
                time: 'timestamp', timeType: 'double', players: ['user_id'], room: 'room_id', search: ['message'],
                columns: ['id', 'user_id', 'room_id', 'message', 'timestamp'],
                describe: fn (stdClass $row, array $refs, array $rooms): array => [
                    'parts' => [self::p($row->user_id), 'said', ['quote' => (string) $row->message], self::r($row->room_id, null, $rooms)],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'room_id' => $row->room_id, 'message' => $row->message],
                ],
            ),
            new LogSource(
                key: 'chatlogs_console', group: 'chat', kind: 'chat', permission: 'manage_private_chatlogs',
                time: 'timestamp', timeType: 'double', players: ['from_id', 'to_id'], room: null, search: ['message'],
                columns: ['id', 'from_id', 'to_id', 'message', 'timestamp'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->from_id), 'sent a private message to', self::p($row->to_id), ['quote' => (string) $row->message]],
                    'raw' => ['id' => $row->id, 'from_id' => $row->from_id, 'to_id' => $row->to_id, 'message' => $row->message],
                ],
            ),
            new LogSource(
                key: 'logs_client_staff', group: 'staff', kind: 'staff', permission: 'manage_commandlogs',
                time: 'timestamp', timeType: 'double', players: ['user_id'], room: null, search: ['data_string'],
                columns: ['id', 'user_id', 'data_string', 'machine_id', 'timestamp'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id), 'used a staff tool', ['quote' => (string) $row->data_string]],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'data_string' => $row->data_string, 'machine_id' => $row->machine_id],
                ],
            ),
            new LogSource(
                key: 'activity_log', group: 'staff', kind: 'staff', permission: 'view_activity_logs',
                time: 'created_at', timeType: 'timestamp', players: ['causer_id'], room: null, search: ['description'],
                columns: ['id', 'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer_id', 'properties', 'created_at'],
                describe: function (stdClass $row): array {
                    /** @var array<string, mixed> $props */
                    $props = json_decode((string) $row->properties, true) ?: [];
                    $parts = [(int) $row->causer_id > 0 ? self::p($row->causer_id) : 'The system', lcfirst((string) $row->description)];

                    if (isset($props['reason']) && is_string($props['reason']) && $props['reason'] !== '') {
                        $parts[] = ['quote' => $props['reason']];
                    }

                    return [
                        'parts' => $parts,
                        'path' => is_string($props['path'] ?? null) ? $props['path'] : null,
                        'raw' => ['id' => $row->id, 'log' => $row->log_name, 'event' => $row->event, 'subject' => $row->subject_type !== null ? class_basename((string) $row->subject_type) . ' ' . $row->subject_id : '', 'properties' => (string) $row->properties],
                    ];
                },
                forPlayer: function (Builder $query, int $player): void {
                    $query->where(function (Builder $where) use ($player): void {
                        $where->where('causer_id', $player)
                            ->orWhere(fn (Builder $subject) => $subject->where('subject_type', User::class)->where('subject_id', (string) $player));
                    });
                },
            ),
            new LogSource(
                key: 'logs_client_trade', group: 'money', kind: 'trade', permission: null,
                time: 'timestamp', timeType: 'char', players: ['1id', '2id'], room: null, search: ['1items', '2items'],
                columns: ['id', '1id', '2id', '1items', '2items', 'timestamp'],
                describe: function (stdClass $row): array {
                    $a = array_values(array_filter(explode(';', (string) $row->{'1items'})));
                    $b = array_values(array_filter(explode(';', (string) $row->{'2items'})));

                    return [
                        'parts' => [self::p($row->{'1id'}), sprintf('traded %d %s for %d %s with', count($a), count($a) === 1 ? 'item' : 'items', count($b), count($b) === 1 ? 'item' : 'items'), self::p($row->{'2id'})],
                        'raw' => ['id' => $row->id, 'user_one_id' => $row->{'1id'}, 'user_two_id' => $row->{'2id'}, 'user_one_items' => $row->{'1items'}, 'user_two_items' => $row->{'2items'}],
                    ];
                },
            ),
            new LogSource(
                key: 'rp_trades', group: 'money', kind: 'trade', permission: null,
                time: 'created_at', timeType: 'int', players: ['user_a', 'user_b'], room: 'room_id', search: ['gave_a', 'gave_b', 'name_a', 'name_b'],
                columns: ['id', 'user_a', 'name_a', 'user_b', 'name_b', 'room_id', 'room_name', 'gave_a', 'gave_b', 'created_at'],
                describe: fn (stdClass $row, array $refs, array $rooms, ?int $me = null): array => [
                    'parts' => [self::p($row->user_a, (string) $row->name_a), sprintf('traded %s for %s with', self::backpack((string) $row->gave_a), self::backpack((string) $row->gave_b)), self::p($row->user_b, (string) $row->name_b), self::r($row->room_id, (string) $row->room_name, $rooms)],
                    'ledger' => $me === (int) $row->user_b
                        ? ['book' => 'Trade', 'what' => sprintf('Gave %s for %s', self::backpack((string) $row->gave_b), self::backpack((string) $row->gave_a)), 'other' => (int) $row->user_a, 'amount' => null, 'after' => null]
                        : ['book' => 'Trade', 'what' => sprintf('Gave %s for %s', self::backpack((string) $row->gave_a), self::backpack((string) $row->gave_b)), 'other' => (int) $row->user_b, 'amount' => null, 'after' => null],
                    'raw' => ['id' => $row->id, 'user_a' => $row->user_a . ' (' . $row->name_a . ')', 'user_b' => $row->user_b . ' (' . $row->name_b . ')', 'gave_a' => $row->gave_a, 'gave_b' => $row->gave_b, 'room' => $row->room_id . ' (' . $row->room_name . ')'],
                ],
            ),
            new LogSource(
                key: 'rp_coin_ledger', group: 'money', kind: 'money', permission: null,
                time: 'created_at', timeType: 'int', players: ['user_id'], room: null, search: ['source'],
                columns: ['id', 'user_id', 'username', 'amount', 'balance_after', 'source', 'created_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), (int) $row->amount >= 0 ? 'got' : 'spent', ['amount' => abs((int) $row->amount)], 'coins · ' . $row->source],
                    'amount' => ['value' => (int) $row->amount, 'owner' => (int) $row->user_id, 'after' => (int) $row->balance_after, 'account' => 'hand'],
                    'ledger' => ['book' => 'Coins', 'what' => (string) $row->source, 'other' => null, 'amount' => (int) $row->amount, 'after' => (int) $row->balance_after],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'amount' => $row->amount, 'balance_after' => $row->balance_after, 'source' => $row->source],
                ],
            ),
            new LogSource(
                key: 'rp_bank_transactions', group: 'money', kind: 'money', permission: null,
                time: 'created_at', timeType: 'int', players: ['user_id'], room: null, search: ['source', 'kind'],
                columns: ['id', 'user_id', 'username', 'kind', 'account', 'amount', 'balance_after', 'source', 'created_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), self::bankVerb((string) $row->kind, (int) $row->amount), ['amount' => abs((int) $row->amount)], sprintf('· %s account%s', $row->account, (string) $row->source !== '' ? ' · ' . $row->source : '')],
                    'amount' => ['value' => (int) $row->amount, 'owner' => (int) $row->user_id, 'after' => (int) $row->balance_after, 'account' => (string) $row->account],
                    'ledger' => ['book' => ucfirst((string) $row->account), 'what' => ucfirst(self::bankVerb((string) $row->kind, (int) $row->amount)) . ((string) $row->source !== '' ? ' · ' . $row->source : ''), 'other' => null, 'amount' => (int) $row->amount, 'after' => (int) $row->balance_after],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'kind' => $row->kind, 'account' => $row->account, 'amount' => $row->amount, 'balance_after' => $row->balance_after, 'source' => $row->source],
                ],
            ),
            new LogSource(
                key: 'rp_pay_transfers', group: 'money', kind: 'money', permission: null,
                time: 'created_at', timeType: 'int', players: ['sender_id', 'recipient_id'], room: null, search: ['note'],
                columns: ['id', 'sender_id', 'recipient_id', 'amount', 'note', 'created_at', 'delivered'],
                describe: fn (stdClass $row, array $refs, array $rooms, ?int $me = null): array => [
                    'parts' => array_values(array_filter([self::p($row->sender_id), 'paid', self::p($row->recipient_id), ['amount' => (int) $row->amount], (string) $row->note !== '' ? ['quote' => (string) $row->note] : null])),
                    'amount' => ['value' => (int) $row->amount, 'from' => (int) $row->sender_id, 'to' => (int) $row->recipient_id],
                    'ledger' => $me === (int) $row->recipient_id
                        ? ['book' => 'Transfer', 'what' => 'Received' . ((string) $row->note !== '' ? ' · ' . $row->note : ''), 'other' => (int) $row->sender_id, 'amount' => (int) $row->amount, 'after' => null]
                        : ['book' => 'Transfer', 'what' => 'Sent' . ((string) $row->note !== '' ? ' · ' . $row->note : ''), 'other' => (int) $row->recipient_id, 'amount' => -(int) $row->amount, 'after' => null],
                    'raw' => ['id' => $row->id, 'sender_id' => $row->sender_id, 'recipient_id' => $row->recipient_id, 'amount' => $row->amount, 'note' => $row->note, 'delivered' => $row->delivered],
                ],
            ),
            new LogSource(
                key: 'rp_bin_search_log', group: 'money', kind: 'money', permission: null,
                time: 'created_at', timeType: 'int', players: ['user_id'], room: 'room_id', search: ['reward'],
                columns: ['id', 'user_id', 'room_id', 'item_id', 'reward', 'amount', 'given', 'created_at'],
                describe: fn (stdClass $row, array $refs, array $rooms): array => [
                    'parts' => [self::p($row->user_id), (string) $row->reward === 'nothing' || (int) $row->amount === 0 ? 'searched a bin and found nothing' : ((string) $row->reward === 'coins' ? 'searched a bin and found' : sprintf('searched a bin and found %d %s', (int) $row->amount, str_replace('_', ' ', (string) $row->reward))), ...((string) $row->reward === 'coins' && (int) $row->amount > 0 ? [['amount' => (int) $row->amount]] : []), self::r($row->room_id, null, $rooms)],
                    'amount' => (string) $row->reward === 'coins' ? ['value' => (int) $row->given, 'owner' => (int) $row->user_id] : null,
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'item_id' => $row->item_id, 'reward' => $row->reward, 'amount' => $row->amount, 'given' => $row->given],
                ],
            ),
            new LogSource(
                key: 'rp_charges', group: 'justice', kind: 'justice', permission: null,
                time: 'charged_at', timeType: 'int', players: ['officer_id', 'user_id'], room: null, search: [],
                columns: ['id', 'user_id', 'crime_id', 'officer_id', 'charged_at', 'dropped_at'],
                describe: function (stdClass $row): array {
                    $crime = self::crimeNames()[(int) $row->crime_id] ?? 'crime ' . $row->crime_id;
                    $parts = (int) $row->officer_id > 0 ? [self::p($row->officer_id), 'charged', self::p($row->user_id), 'with ' . strtolower($crime)] : [self::p($row->user_id), 'was charged with ' . strtolower($crime) . ' automatically'];

                    if ((int) $row->dropped_at > 0) {
                        $parts[] = '· since cleared';
                    }

                    return ['parts' => $parts, 'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'crime' => $row->crime_id . ' (' . $crime . ')', 'officer_id' => $row->officer_id, 'dropped_at' => $row->dropped_at]];
                },
            ),
            new LogSource(
                key: 'rp_911_calls', group: 'justice', kind: 'justice', permission: null,
                time: 'created_at', timeType: 'int', players: ['caller_id', 'responder_id'], room: 'room_id', search: ['message', 'room_name'],
                columns: ['id', 'caller_id', 'caller_name', 'room_id', 'room_name', 'message', 'created_at', 'responder_id', 'responder_name', 'responded_at', 'kind', 'mark'],
                describe: function (stdClass $row, array $refs, array $rooms): array {
                    $parts = (int) $row->kind === 1
                        ? [self::p($row->caller_id, (string) $row->caller_name), 'asked for backup']
                        : [self::p($row->caller_id, (string) $row->caller_name), 'called 911', ['quote' => (string) $row->message]];
                    $parts[] = self::r($row->room_id, (string) $row->room_name, $rooms);

                    if ((int) $row->responder_id > 0) {
                        $parts[] = '· answered by';
                        $parts[] = self::p($row->responder_id, (string) $row->responder_name);
                    }

                    return ['parts' => $parts, 'raw' => ['id' => $row->id, 'kind' => (int) $row->kind === 1 ? 'backup' : '911', 'caller' => $row->caller_id . ' (' . $row->caller_name . ')', 'responder' => (int) $row->responder_id > 0 ? $row->responder_id . ' (' . $row->responder_name . ')' : 'none', 'responded_at' => $row->responded_at, 'mark' => match ((int) $row->mark) {
                        1 => 'helpful', 2 => 'abuse', default => ''
                    }]];
                },
            ),
            new LogSource(
                key: 'rp_jail', group: 'justice', kind: 'justice', permission: null,
                time: 'jailed_at', timeType: 'int', players: ['officer_id', 'user_id'], room: 'room_id', search: ['charges'],
                columns: ['id', 'user_id', 'officer_id', 'room_id', 'sentence_seconds', 'jailed_at', 'released_at', 'charges'],
                describe: fn (stdClass $row, array $refs, array $rooms): array => [
                    'parts' => [self::p($row->officer_id), 'jailed', self::p($row->user_id), sprintf('for %dm', intdiv((int) $row->sentence_seconds + 59, 60)), ['quote' => implode(', ', self::chargeLines((string) $row->charges))], self::r($row->room_id, null, $rooms)],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'officer_id' => $row->officer_id, 'sentence_seconds' => $row->sentence_seconds, 'released_at' => $row->released_at, 'charges' => str_replace("\t", '× ', (string) $row->charges)],
                ],
            ),
            new LogSource(
                key: 'rp_furni_function_log', group: 'furni', kind: 'furni', permission: null,
                time: 'changed_at', timeType: 'timestamp', players: ['user_id'], room: null, search: ['item_name', 'field', 'new_value'],
                columns: ['id', 'definition_id', 'item_name', 'user_id', 'username', 'field', 'old_value', 'new_value', 'changed_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), sprintf('set %s on %s to', str_replace('_', ' ', (string) $row->field), $row->item_name), ['quote' => (string) $row->new_value === '' ? 'nothing' : (string) $row->new_value]],
                    'raw' => ['id' => $row->id, 'definition_id' => $row->definition_id, 'item_name' => $row->item_name, 'field' => $row->field, 'old_value' => $row->old_value, 'new_value' => $row->new_value],
                ],
            ),
            new LogSource(
                key: 'rp_furni_delete_log', group: 'furni', kind: 'furni', permission: 'manage_furni_delete_logs',
                time: 'deleted_at', timeType: 'timestamp', players: ['user_id'], room: null, search: ['item_name'],
                columns: ['id', 'item_id', 'definition_id', 'item_name', 'user_id', 'username', 'deleted_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), sprintf('deleted %s', $row->item_name)],
                    'raw' => ['id' => $row->id, 'item_id' => $row->item_id, 'definition_id' => $row->definition_id, 'item_name' => $row->item_name],
                ],
            ),
            new LogSource(
                key: 'rp_catalog_move_log', group: 'furni', kind: 'furni', permission: null,
                time: 'moved_at', timeType: 'timestamp', players: ['user_id'], room: null, search: ['item_name', 'from_caption', 'to_caption'],
                columns: ['id', 'catalog_item_id', 'definition_id', 'item_name', 'from_page_id', 'from_caption', 'to_page_id', 'to_caption', 'user_id', 'username', 'moved_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), sprintf('moved %s from %s to %s in the catalog', $row->item_name, $row->from_caption, $row->to_caption)],
                    'raw' => ['id' => $row->id, 'catalog_item_id' => $row->catalog_item_id, 'item_name' => $row->item_name, 'from_page' => $row->from_page_id . ' (' . $row->from_caption . ')', 'to_page' => $row->to_page_id . ' (' . $row->to_caption . ')'],
                ],
            ),
            new LogSource(
                key: 'website_login_logs', group: 'accounts', kind: 'login', permission: 'edit_user',
                time: 'created_at', timeType: 'timestamp', players: ['user_id'], room: null, search: ['ip_address', 'username', 'user_agent'],
                columns: ['id', 'user_id', 'username', 'ip_address', 'user_agent', 'succeeded', 'method', 'created_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [(int) $row->user_id > 0 ? self::p($row->user_id, (string) $row->username) : ['quote' => (string) $row->username], (bool) $row->succeeded ? sprintf('logged in from %s · %s', $row->ip_address, self::browser((string) $row->user_agent)) : sprintf('failed to log in from %s', $row->ip_address)],
                    'raw' => ['id' => $row->id, 'username' => $row->username, 'ip_address' => $row->ip_address, 'method' => $row->method, 'succeeded' => (bool) $row->succeeded ? 'yes' : 'no', 'user_agent' => $row->user_agent],
                ],
            ),
            new LogSource(
                key: 'rp_browser_guard_log', group: 'accounts', kind: 'guard', permission: 'edit_user',
                time: 'created_at', timeType: 'int', players: ['user_id'], room: null, search: ['detail', 'username'],
                columns: ['id', 'user_id', 'username', 'reason', 'detail', 'created_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), 'tripped the browser guard · ' . strtolower(self::guardReason((int) $row->reason)), ...((string) $row->detail !== '' ? [['quote' => (string) $row->detail]] : [])],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'reason' => $row->reason . ' (' . self::guardReason((int) $row->reason) . ')', 'detail' => $row->detail],
                ],
            ),
            new LogSource(
                key: 'logs_client_namechange', group: 'accounts', kind: 'login', permission: null,
                time: 'timestamp', timeType: 'int', players: ['user_id'], room: null, search: ['old_name', 'new_name'],
                columns: ['id', 'user_id', 'old_name', 'new_name', 'timestamp'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id), sprintf('changed name from %s to %s', $row->old_name, $row->new_name)],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'old_name' => $row->old_name, 'new_name' => $row->new_name],
                ],
            ),
            new LogSource(
                key: 'rp_packet_flood_log', group: 'accounts', kind: 'guard', permission: null,
                time: 'created_at', timeType: 'int', players: ['user_id'], room: null, search: ['packet', 'username'],
                columns: ['id', 'user_id', 'username', 'packet', 'dropped', 'window_seconds', 'created_at'],
                describe: fn (stdClass $row): array => [
                    'parts' => [self::p($row->user_id, (string) $row->username), sprintf('was disconnected for flooding %s (%d dropped in %ds)', $row->packet, (int) $row->dropped, (int) $row->window_seconds)],
                    'raw' => ['id' => $row->id, 'user_id' => $row->user_id, 'packet' => $row->packet, 'dropped' => $row->dropped, 'window_seconds' => $row->window_seconds],
                ],
            ),
        ];
    }

    private static function bankVerb(string $kind, int $amount): string
    {
        return match ($kind) {
            'deposit' => 'deposited',
            'withdraw' => 'withdrew',
            'wages' => 'was paid wages of',
            'interest' => 'earned interest of',
            'transfer_in' => 'received a transfer of',
            'transfer_out' => 'sent a transfer of',
            'pay_in' => 'was paid',
            'pay_out' => 'paid out',
            'open' => 'opened a bank account with',
            default => $amount >= 0 ? 'got' : 'spent',
        };
    }

    /**
     * rp_jail.charges: one "count<TAB>crime name" line per crime.
     *
     * @return list<string>
     */
    public static function chargeLines(string $charges): array
    {
        $out = [];

        foreach (preg_split('/\R/', trim($charges)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$count, $name] = array_pad(explode("\t", $line, 2), 2, '');

            if ($name === '') {
                $out[] = trim($count);

                continue;
            }

            $out[] = (int) $count > 1 ? sprintf('%d× %s', (int) $count, trim($name)) : trim($name);
        }

        return $out;
    }
}
