<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Corporations: the list and one corporation's ladder, employees, stock,
 * prices, HQ rooms and uniforms. All of it changes in game (the City Panel
 * and the corp commands); the panel only reads.
 */
class CorporationController extends Controller
{
    /**
     * The domain colour a corporation draws with: police, medical or a
     * plain business.
     */
    public static function domain(bool $police, string $service): string
    {
        return match (true) {
            $police || $service === 'police' => 'police',
            $service === 'medical' => 'medical',
            default => 'corp',
        };
    }

    public function index(): JsonResponse
    {
        if (! RoleplaySchema::has('rp_corporations')) {
            return response()->json(['items' => []]);
        }

        $counts = RoleplaySchema::has('rp_corporation_employees')
            ? DB::table('rp_corporation_employees')->groupBy('corporation_id')->selectRaw('corporation_id, COUNT(*) AS n, SUM(on_duty = 1) AS on_duty, SUM(shift_seconds_week) AS week')->get()->keyBy('corporation_id')
            : collect();
        $ranks = RoleplaySchema::has('rp_corporation_ranks')
            ? DB::table('rp_corporation_ranks')->groupBy('corporation_id')->selectRaw('corporation_id, COUNT(*) AS n')->pluck('n', 'corporation_id')
            : collect();
        $hq = DB::table('rooms')->where('corporation_id', '>', 0)->groupBy('corporation_id')->selectRaw('corporation_id, COUNT(*) AS n')->pluck('n', 'corporation_id');

        return response()->json([
            'items' => DB::table('rp_corporations')->orderBy('sort_order')->orderBy('name')->get()->map(function (stdClass $c) use ($counts, $ranks, $hq): array {
                $count = $counts->get($c->id);

                return [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'acronym' => (string) $c->acronym,
                    'badge' => (string) $c->badge,
                    'domain' => self::domain((bool) $c->is_police, (string) $c->service_type),
                    'hidden' => (bool) $c->hidden,
                    'employees' => (int) ($count->n ?? 0),
                    'on_duty' => (int) ($count->on_duty ?? 0),
                    'shift_week' => (int) ($count->week ?? 0),
                    'ranks' => (int) ($ranks->get($c->id) ?? 0),
                    'hq_rooms' => (int) ($hq->get($c->id) ?? 0),
                    'stock' => (int) $c->stock,
                    'stock_capacity' => (int) $c->stock_capacity,
                ];
            })->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        abort_unless(RoleplaySchema::has('rp_corporations'), 404);
        $c = DB::table('rp_corporations')->where('id', $id)->first();
        abort_if($c === null, 404);

        $ranks = RoleplaySchema::has('rp_corporation_ranks') ? DB::table('rp_corporation_ranks')->where('corporation_id', $id)->orderByDesc('rank_order')->get() : collect();
        $employees = RoleplaySchema::has('rp_corporation_employees')
            ? DB::table('rp_corporation_employees as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->where('e.corporation_id', $id)->orderByDesc('e.on_duty')->orderByDesc('e.shift_seconds_week')->get(['e.*', 'u.online'])
            : collect();
        $refs = PlayerRefs::many($employees->pluck('user_id'));
        $rankNames = $ranks->pluck('name', 'id');
        $headcount = $employees->groupBy('rank_id')->map->count();
        $title = RoleplaySchema::roomTitleColumn();
        $hqRooms = DB::table('rooms')->where('corporation_id', $id)->orderBy('id')->get(['id', $title . ' as name', 'users_now']);
        $access = RoleplaySchema::has('rp_hq_room_ranks') && $hqRooms->isNotEmpty()
            ? DB::table('rp_hq_room_ranks as h')->join('rp_corporation_ranks as r', 'r.id', '=', 'h.rank_id')->whereIn('h.room_id', $hqRooms->pluck('id'))->get(['h.room_id', 'r.name', 'r.rank_order'])->groupBy('room_id')
            : collect();
        $samples = RoleplaySchema::has('rp_corporation_stock_samples')
            ? DB::table('rp_corporation_stock_samples')->where('corporation_id', $id)->where('sampled_at', '>=', time() - 14 * 86400)->orderBy('sampled_at')->get(['value', 'sampled_at'])
            : collect();
        $uniforms = RoleplaySchema::has('rp_uniforms') && $ranks->isNotEmpty()
            ? DB::table('rp_uniforms')->where('kind', 'rank')->whereIn('rank_id', $ranks->pluck('id'))->get(['rank_id', 'gender', 'figure'])->groupBy('rank_id')
            : collect();
        $week = time() - 7 * 86400;
        $wageSources = array_values(array_unique(array_filter(['Wage - ' . $c->name, (string) $c->acronym !== '' ? 'Wage - ' . $c->acronym : null])));
        $payroll = RoleplaySchema::has('rp_coin_ledger') ? (int) DB::table('rp_coin_ledger')->whereIn('source', $wageSources)->where('created_at', '>=', $week)->sum('amount') : 0;
        $lastRestock = null;

        $previous = null;

        foreach ($samples as $sample) {
            if ($previous !== null && (int) $sample->value > $previous) {
                $lastRestock = (int) $sample->sampled_at;
            }

            $previous = (int) $sample->value;
        }

        $total = $ranks->count();

        return response()->json([
            'corporation' => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'acronym' => (string) $c->acronym,
                'description' => (string) $c->description,
                'badge' => (string) $c->badge,
                'domain' => self::domain((bool) $c->is_police, (string) $c->service_type),
                'service_type' => (string) $c->service_type,
                'police' => (bool) $c->is_police,
                'hidden' => (bool) $c->hidden,
                'stock' => (int) $c->stock,
                'stock_capacity' => (int) $c->stock_capacity,
                'manage_rank_order' => (int) $c->manage_rank_order,
            ],
            'stats' => [
                'employees' => $employees->count(),
                'ranks' => $total,
                'on_duty' => array_values($employees->where('on_duty', 1)->map(fn (stdClass $e): ?array => PlayerRefs::pick($refs, $e->user_id))->filter()->all()),
                'shift_week' => (int) $employees->sum('shift_seconds_week'),
                'payroll_week' => $payroll,
                'last_restock' => $lastRestock,
            ],
            'ladder' => $ranks->map(fn (stdClass $r): array => [
                'id' => (int) $r->id,
                'order' => (int) $r->rank_order,
                'name' => (string) $r->name,
                'pay' => (int) $r->pay,
                'tiers' => (int) $r->tiers,
                'emergency' => (bool) $r->emergency_eligible,
                'manages' => (int) $r->rank_order >= (int) $c->manage_rank_order && (int) $c->manage_rank_order > 0,
                'headcount' => (int) ($headcount->get($r->id) ?? 0),
            ])->values(),
            'employees' => $employees->map(fn (stdClass $e): array => [
                'player' => PlayerRefs::pick($refs, $e->user_id),
                'rank' => (string) ($rankNames->get($e->rank_id) ?? '—'),
                'tier' => (int) $e->tier,
                'on_duty' => (int) $e->on_duty === 1,
                'online' => (string) $e->online === '1',
                'shift_week' => (int) $e->shift_seconds_week,
                'shift_total' => (int) $e->shift_seconds,
                'hired_at' => (int) $e->hired_at,
            ])->values(),
            'stock' => $samples->map(fn (stdClass $s): array => ['at' => (int) $s->sampled_at, 'value' => (int) $s->value])->values(),
            'prices' => RoleplaySchema::has('rp_service_prices') ? DB::table('rp_service_prices')->where('corporation_id', $id)->orderBy('sort_order')->get(['key', 'name', 'price'])->map(fn (stdClass $p): array => ['key' => (string) $p->key, 'name' => (string) $p->name, 'price' => (int) $p->price])->values() : [],
            'hq' => $hqRooms->map(function (stdClass $room) use ($access): array {
                $ranks = $access->get($room->id);
                $lowest = $ranks?->sortBy('rank_order')->first();

                return [
                    'id' => (int) $room->id,
                    'name' => (string) $room->name,
                    'now' => (int) $room->users_now,
                    'lowest' => $lowest !== null ? (string) $lowest->name : null,
                    'ranks' => $ranks?->count() ?? 0,
                ];
            })->values(),
            'uniforms' => $ranks->map(fn (stdClass $r): array => [
                'rank' => (string) $r->name,
                'male' => (string) ($uniforms->get($r->id)?->firstWhere('gender', 'M')->figure ?? ''),
                'female' => (string) ($uniforms->get($r->id)?->firstWhere('gender', 'F')->figure ?? ''),
            ])->values(),
        ]);
    }
}
