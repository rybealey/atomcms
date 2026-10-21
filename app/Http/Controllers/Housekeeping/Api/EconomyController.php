<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Where coins enter the city, move around and leave: the coin ledger
 * (rp_coin_ledger, every change to coins on hand) and the bank's
 * (rp_bank_transactions). Moves between a player's hand and their bank, and
 * money changing hands between players, are neither created nor removed.
 */
class EconomyController extends Controller
{
    /** Categories that only move money that already exists. */
    private const MOVES = ['Bank moves', 'Transfers', 'Marketplace and trades'];

    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    /**
     * What a coin ledger source counts as. The sources are the emulator's own
     * strings (CoinLedger.Record callers).
     */
    public static function category(string $source): string
    {
        return match (true) {
            str_starts_with($source, 'Wage') => 'Wages',
            str_starts_with($source, 'Bank ') => 'Bank moves',
            $source === 'Searched a bin' => 'Bin rewards',
            str_contains($source, 'by RCON') || str_contains($source, 'by staff') || $source === 'Reward' || $source === 'Voucher' => 'Staff gifts',
            str_starts_with($source, 'Given by ') || str_starts_with($source, 'Gave to ') => 'Transfers',
            str_starts_with($source, 'Marketplace') || str_starts_with($source, 'Sold to') || str_starts_with($source, 'Bought from') || str_contains($source, 'credit furni') => 'Marketplace and trades',
            str_starts_with($source, 'Catalog') || $source === 'Clothing store' || $source === 'Group purchase' => 'Furni and catalog',
            str_contains($source, 'Gym') || str_contains($source, 'membership') => 'Gym and memberships',
            str_starts_with($source, 'Gang ') => 'Gangs',
            stripos($source, 'ticket') !== false || stripos($source, 'fine') !== false || stripos($source, 'bail') !== false => 'Fines and tickets',
            default => 'Shops and services',
        };
    }

    public function __invoke(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '8d');
        $days = match ($range) {
            'today' => 1,
            '30d' => 30,
            default => 8,
        };
        /** @var User $actor */
        $actor = $request->user();
        $payments = $this->permissions->allows($actor, 'view_payments');

        /** @var array<string, mixed> $data */
        $data = Cache::remember('housekeeping:economy:' . $days, now()->addMinute(), fn (): array => $this->build($days));

        if ($payments && RoleplaySchema::has('website_diamond_orders')) {
            $week = DB::table('website_diamond_orders')->where('status', 'paid')->where('paid_at', '>=', now()->subDays(7))->selectRaw('SUM(amount_cents) AS cents, COUNT(*) AS n')->first();
            $data['diamond_sales'] = ['cents' => (int) ($week->cents ?? 0), 'orders' => (int) ($week->n ?? 0)];
        } else {
            $data['diamond_sales'] = null;
        }

        $data['payments_rank'] = (int) (DB::table('website_housekeeping_permissions')->where('permission', 'view_payments')->value('min_rank') ?? 0);

        return response()->json($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $days): array
    {
        $today = Carbon::today()->timestamp;
        $start = Carbon::today()->subDays($days - 1)->timestamp;
        $created = array_fill(0, $days, 0);
        $removed = array_fill(0, $days, 0);
        $sources = [];
        $count = function (int $day, string $category, int $amount) use (&$created, &$removed, &$sources, $days): void {
            if (in_array($category, self::MOVES, true) || $day < 0 || $day >= $days) {
                return;
            }

            if ($amount >= 0) {
                $created[$day] += $amount;
            } else {
                $removed[$day] -= $amount;
            }

            $sources[$category] = ($sources[$category] ?? 0) + $amount;
        };

        if (RoleplaySchema::has('rp_coin_ledger')) {
            $rows = DB::table('rp_coin_ledger')
                ->where('created_at', '>=', $start)
                ->selectRaw('FLOOR((created_at - ?) / 86400) AS d, source, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS plus, SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) AS minus', [$start])
                ->groupBy('d', 'source')
                ->get();

            foreach ($rows as $row) {
                $category = self::category((string) $row->source);
                $count((int) $row->d, $category, (int) $row->plus);
                $count((int) $row->d, $category, (int) $row->minus);
            }
        }

        if (RoleplaySchema::has('rp_bank_transactions')) {
            $rows = DB::table('rp_bank_transactions')
                ->where('created_at', '>=', $start)
                ->whereIn('kind', ['wages', 'interest'])
                ->selectRaw('FLOOR((created_at - ?) / 86400) AS d, kind, SUM(amount) AS total', [$start])
                ->groupBy('d', 'kind')
                ->get();

            foreach ($rows as $row) {
                $count((int) $row->d, (string) $row->kind === 'wages' ? 'Wages' : 'Savings interest', (int) $row->total);
            }
        }

        $onHand = (int) DB::table('users')->sum('credits');
        $bank = RoleplaySchema::has('rp_bank_accounts') ? DB::table('rp_bank_accounts')->selectRaw('SUM(current_balance) AS c, SUM(savings_balance) AS s')->first() : null;
        uasort($sources, fn (int $a, int $b): int => [$b > 0, abs($b)] <=> [$a > 0, abs($a)]);

        return [
            'days' => array_map(fn (int $i): array => ['day' => (int) $start + $i * 86400, 'created' => $created[$i], 'removed' => $removed[$i]], range(0, $days - 1)),
            'totals' => [
                'on_hand' => $onHand,
                'bank_current' => (int) ($bank->c ?? 0),
                'bank_savings' => (int) ($bank->s ?? 0),
                'created_today' => $created[$days - 1],
                'removed_today' => $removed[$days - 1],
                'today' => $today,
            ],
            'sources' => array_map(fn (string $name, int $amount): array => ['name' => $name, 'amount' => $amount], array_keys($sources), array_values($sources)),
            'largest' => $this->largest(),
            'bins' => RoleplaySchema::has('rp_bin_rewards')
                ? DB::table('rp_bin_rewards')->orderBy('sort_order')->get()->map(fn (stdClass $b): array => ['reward' => (string) $b->reward, 'weight' => (int) $b->weight, 'min' => (int) $b->min_amount, 'max' => (int) $b->max_amount])->values()->all()
                : [],
            'prices' => RoleplaySchema::has('rp_service_prices')
                ? DB::table('rp_service_prices as p')->leftJoin('rp_corporations as c', 'c.id', '=', 'p.corporation_id')->orderBy('p.sort_order')->get(['p.key', 'p.name', 'p.price', 'c.name as corporation'])->map(fn (stdClass $p): array => ['key' => (string) $p->key, 'name' => (string) $p->name, 'price' => (int) $p->price, 'corporation' => $p->corporation])->values()->all()
                : [],
        ];
    }

    /**
     * The biggest single movements in the last day across the ledgers.
     *
     * @return list<array<string, mixed>>
     */
    private function largest(): array
    {
        $since = time() - 86400;
        $rows = [];

        if (RoleplaySchema::has('rp_pay_transfers')) {
            foreach (DB::table('rp_pay_transfers')->where('created_at', '>=', $since)->orderByDesc('amount')->limit(8)->get() as $t) {
                $rows[] = ['at' => (int) $t->created_at, 'from' => (int) $t->sender_id, 'to' => (int) $t->recipient_id, 'book' => 'Transfer', 'amount' => (int) $t->amount, 'what' => (string) $t->note];
            }
        }

        if (RoleplaySchema::has('rp_coin_ledger')) {
            foreach (DB::table('rp_coin_ledger')->where('created_at', '>=', $since)->orderByRaw('ABS(amount) DESC')->limit(30)->get() as $c) {
                // A player's own hand-to-bank moves and gifts to each other
                // (shown from rp_pay_transfers) don't enter or leave the city.
                if (in_array(self::category((string) $c->source), ['Bank moves', 'Transfers'], true)) {
                    continue;
                }

                $rows[] = (int) $c->amount >= 0
                    ? ['at' => (int) $c->created_at, 'from' => null, 'to' => (int) $c->user_id, 'book' => 'Coins', 'amount' => (int) $c->amount, 'what' => (string) $c->source]
                    : ['at' => (int) $c->created_at, 'from' => (int) $c->user_id, 'to' => null, 'book' => 'Coins', 'amount' => -(int) $c->amount, 'what' => (string) $c->source];
            }
        }

        if (RoleplaySchema::has('rp_bank_transactions')) {
            foreach (DB::table('rp_bank_transactions')->where('created_at', '>=', $since)->whereNotIn('kind', ['deposit', 'withdraw'])->orderByRaw('ABS(amount) DESC')->limit(8)->get() as $b) {
                $rows[] = (int) $b->amount >= 0
                    ? ['at' => (int) $b->created_at, 'from' => null, 'to' => (int) $b->user_id, 'book' => ucfirst((string) $b->account), 'amount' => (int) $b->amount, 'what' => trim(ucfirst(str_replace('_', ' ', (string) $b->kind)) . ' ' . $b->source)]
                    : ['at' => (int) $b->created_at, 'from' => (int) $b->user_id, 'to' => null, 'book' => ucfirst((string) $b->account), 'amount' => -(int) $b->amount, 'what' => trim(ucfirst(str_replace('_', ' ', (string) $b->kind)) . ' ' . $b->source)];
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        $rows = array_slice($rows, 0, 10);
        $refs = PlayerRefs::many(array_merge(array_column($rows, 'from'), array_column($rows, 'to')));

        return array_map(fn (array $r): array => ['from' => $r['from'] !== null ? PlayerRefs::pick($refs, $r['from']) : null, 'to' => $r['to'] !== null ? PlayerRefs::pick($refs, $r['to']) : null] + $r, $rows);
    }
}
