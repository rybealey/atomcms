<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Services\SettingsService;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\HotelResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Catalog and furni: the shop tree (catalog_pages), what each page sells
 * (catalog_items) and the furni definitions behind them (furniture). The
 * emulator caches all three, so every save asks it to reload.
 *
 * Two traps the shop has taught: captions are not unique - pages are always
 * addressed by id - and `visible` is the client's render flag for a page in
 * the tree, not a filter; a page is shipped dark by turning it off.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function tree(): JsonResponse
    {
        $counts = DB::table('catalog_items')->groupBy('page_id')->selectRaw('page_id, COUNT(*) AS n')->pluck('n', 'page_id');
        $pages = DB::table('catalog_pages')
            ->orderBy('order_num')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'caption', 'icon_image', 'min_rank', 'order_num', 'page_layout', 'visible', 'enabled'])
            ->map(fn (object $p): array => [
                'id' => (int) $p->id,
                'parent' => (int) $p->parent_id,
                'caption' => (string) $p->caption,
                'icon' => (int) $p->icon_image,
                'min_rank' => (int) $p->min_rank,
                'order' => (int) $p->order_num,
                'layout' => (string) $p->page_layout,
                'visible' => self::bit($p->visible),
                'enabled' => self::bit($p->enabled),
                'items' => (int) ($counts->get($p->id) ?? 0),
            ])
            ->values();

        return response()->json([
            'pages' => $pages,
            'icons_path' => rtrim((string) $this->settings->getOrDefault('catalog_icons_path', ''), '/'),
        ]);
    }

    public function page(int $id): JsonResponse
    {
        $page = DB::table('catalog_pages')->where('id', $id)->first();
        abort_if($page === null, 404);
        $parent = (int) $page->parent_id > 0 ? DB::table('catalog_pages')->where('id', $page->parent_id)->value('caption') : null;
        $items = DB::table('catalog_items')->where('page_id', $id)->orderBy('id')->get();
        $furniIds = $items->pluck('item_id')->filter(fn (mixed $v): bool => ctype_digit((string) $v))->map(fn (mixed $v): int => (int) $v)->unique()->values();
        $furni = $furniIds->isNotEmpty()
            ? DB::table('furniture')->whereIn('id', $furniIds)->get(['id', 'item_name', 'public_name', 'type', 'interaction_type', 'interaction_modes_count', 'vending_ids', 'allow_trade', 'allow_inventory_stack', 'is_rare', 'sprite_id'])->keyBy('id')
            : collect();
        $placed = RoleplaySchema::has('rp_item_function') && $furniIds->isNotEmpty()
            ? DB::table('rp_item_function as f')->join('items as i', 'i.id', '=', 'f.item_id')->whereIn('i.base_item', $furniIds)->groupBy('i.base_item')->selectRaw('i.base_item, COUNT(DISTINCT f.item_id) AS n')->pluck('n', 'base_item')
            : collect();
        $sold = RoleplaySchema::has('rp_coin_ledger') && $items->isNotEmpty()
            ? DB::table('rp_coin_ledger')->where('created_at', '>=', time() - 7 * 86400)->whereIn('source', $items->map(fn (object $i): string => 'Catalog - ' . $i->catalog_name)->unique()->values())->groupBy('source')->selectRaw('source, COUNT(*) AS n')->pluck('n', 'source')
            : collect();

        return response()->json([
            'page' => [
                'id' => (int) $page->id,
                'parent' => (int) $page->parent_id,
                'parent_caption' => $parent !== null ? (string) $parent : null,
                'caption' => (string) $page->caption,
                'icon' => (int) $page->icon_image,
                'min_rank' => (int) $page->min_rank,
                'order' => (int) $page->order_num,
                'layout' => (string) $page->page_layout,
                'visible' => self::bit($page->visible),
                'enabled' => self::bit($page->enabled),
            ],
            'icons_path' => rtrim((string) $this->settings->getOrDefault('furniture_icons_path', ''), '/'),
            'items' => $items->map(function (object $i) use ($furni, $placed, $sold): array {
                $f = ctype_digit((string) $i->item_id) ? $furni->get((int) $i->item_id) : null;

                return [
                    'id' => (int) $i->id,
                    'item_id' => (string) $i->item_id,
                    'name' => (string) $i->catalog_name,
                    'credits' => (int) $i->cost_credits,
                    'pixels' => (int) $i->cost_pixels,
                    'diamonds' => (int) $i->cost_diamonds,
                    'amount' => (int) $i->amount,
                    'limited_stack' => (int) $i->limited_stack,
                    'limited_sells' => (int) $i->limited_sells,
                    'active' => (string) $i->offer_active === '1',
                    'sold' => (int) ($sold->get('Catalog - ' . $i->catalog_name) ?? 0),
                    'furni' => $f !== null ? [
                        'id' => (int) $f->id,
                        'item_name' => (string) $f->item_name,
                        'public_name' => (string) $f->public_name,
                        'type' => (string) $f->type,
                        'interaction' => (string) $f->interaction_type,
                        'modes' => (int) $f->interaction_modes_count,
                        'vending_ids' => (string) $f->vending_ids,
                        'trade' => (string) $f->allow_trade === '1',
                        'stack' => (string) $f->allow_inventory_stack === '1',
                        'rare' => (string) $f->is_rare === '1',
                        'overrides' => (int) ($placed->get($f->id) ?? 0),
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function storePage(Request $request): JsonResponse
    {
        $data = $this->validatePage($request);
        $data['order_num'] ??= (int) DB::table('catalog_pages')->where('parent_id', $data['parent_id'])->max('order_num') + 1;
        $id = DB::table('catalog_pages')->insertGetId($data + ['page_layout' => 'default_3x3', 'page_strings_1' => '', 'page_strings_2' => '', 'icon_image' => 1]);
        $reload = Hotel::reload('catalog');
        Audit::record('catalog', sprintf('added the catalog page %s', $data['caption']), target: ['type' => 'catalog_page', 'id' => $id, 'label' => $data['caption']], after: $data, rcon: $reload);

        return $this->saved('Page added', $reload, ['id' => $id]);
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        $before = DB::table('catalog_pages')->where('id', $id)->first(['caption', 'parent_id', 'min_rank', 'order_num', 'visible', 'enabled']);
        abort_if($before === null, 404);
        $data = $this->validatePage($request, $id);
        DB::table('catalog_pages')->where('id', $id)->update($data);
        [$old, $new] = Audit::diff(['caption' => $before->caption, 'parent_id' => (int) $before->parent_id, 'min_rank' => (int) $before->min_rank, 'order_num' => (int) $before->order_num, 'visible' => (int) self::bit($before->visible), 'enabled' => (int) self::bit($before->enabled)], $data);
        $reload = Hotel::reload('catalog');

        if ($new !== []) {
            Audit::record('catalog', sprintf('changed the catalog page %s (#%d)', $data['caption'], $id), target: ['type' => 'catalog_page', 'id' => $id, 'label' => $data['caption']], before: $old, after: $new, rcon: $reload);
        }

        return $this->saved('Page saved', $reload);
    }

    /**
     * Put a page's children in the order given.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent' => ['required', 'integer'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (array_values($data['ids']) as $i => $id) {
                DB::table('catalog_pages')->where('id', (int) $id)->where('parent_id', (int) $data['parent'])->update(['order_num' => $i + 1]);
            }
        });

        $reload = Hotel::reload('catalog');
        Audit::record('catalog', sprintf('reordered the pages under catalog page #%d', $data['parent']), target: ['type' => 'catalog_page', 'id' => (int) $data['parent']], after: ['order' => implode(', ', $data['ids'])], rcon: $reload);

        return $this->saved('Order saved', $reload);
    }

    /**
     * Find furni definitions to add to a page.
     */
    public function furni(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        abort_if(strlen($q) < 2, 422, 'Type at least two characters.');
        $like = '%' . addcslashes($q, '\\%_') . '%';

        return response()->json([
            'items' => DB::table('furniture')
                ->where(fn ($w) => $w->where('item_name', 'like', $like)->orWhere('public_name', 'like', $like)->when(ctype_digit($q), fn ($id) => $id->orWhere('id', (int) $q)))
                ->orderBy('item_name')
                ->limit(20)
                ->get(['id', 'item_name', 'public_name', 'interaction_type'])
                ->map(fn (object $f): array => ['id' => (int) $f->id, 'item_name' => (string) $f->item_name, 'public_name' => (string) $f->public_name, 'interaction' => (string) $f->interaction_type])
                ->values(),
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page_id' => ['required', 'integer', 'exists:catalog_pages,id'],
            'furni_id' => ['required', 'integer', 'exists:furniture,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'credits' => ['required', 'integer', 'min:0', 'max:10000000'],
            'diamonds' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $furni = DB::table('furniture')->where('id', $data['furni_id'])->first(['item_name']);
        $row = [
            'page_id' => (int) $data['page_id'],
            'item_id' => (string) $data['furni_id'],
            'catalog_name' => (string) (($data['name'] ?? '') !== '' ? $data['name'] : ($furni->item_name ?? '')),
            'cost_credits' => (int) $data['credits'],
            'cost_pixels' => 0,
            'cost_diamonds' => (int) $data['diamonds'],
            'amount' => 1,
            'limited_sells' => 0,
            'limited_stack' => 0,
            'offer_active' => '1',
            'extradata' => '',
            'badge' => '',
            'offer_id' => -1,
        ];
        $id = DB::table('catalog_items')->insertGetId($row);
        $reload = Hotel::reload('catalog');
        Audit::record('catalog', sprintf('added %s to catalog page #%d', $row['catalog_name'], $row['page_id']), target: ['type' => 'catalog_item', 'id' => $id, 'label' => $row['catalog_name']], after: $row, rcon: $reload);

        return $this->saved('Item added', $reload, ['id' => $id]);
    }

    /**
     * Save a catalog item and the server-side parts of its furni definition.
     * Names, walkable and seat flags are left alone: the client keeps its own
     * copy of those in FurnitureData, and editing them here would desync it.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'credits' => ['required', 'integer', 'min:0', 'max:10000000'],
            'pixels' => ['required', 'integer', 'min:0', 'max:10000000'],
            'diamonds' => ['required', 'integer', 'min:0', 'max:1000000'],
            'amount' => ['required', 'integer', 'min:1', 'max:100'],
            'limited_stack' => ['required', 'integer', 'min:0', 'max:100000'],
            'active' => ['required', 'boolean'],
            'furni' => ['nullable', 'array'],
            'furni.interaction' => ['required_with:furni', 'string', 'max:25', 'regex:/^[a-z0-9_]+$/'],
            'furni.modes' => ['required_with:furni', 'integer', 'min:0', 'max:100'],
            'furni.vending_ids' => ['nullable', 'string', 'max:255', 'regex:/^[0-9,]*$/'],
            'furni.trade' => ['required_with:furni', 'boolean'],
            'furni.stack' => ['required_with:furni', 'boolean'],
        ]);
        $item = DB::table('catalog_items')->where('id', $id)->first();
        abort_if($item === null, 404);
        $row = [
            'catalog_name' => $data['name'],
            'cost_credits' => (int) $data['credits'],
            'cost_pixels' => (int) $data['pixels'],
            'cost_diamonds' => (int) $data['diamonds'],
            'amount' => (int) $data['amount'],
            'limited_stack' => (int) $data['limited_stack'],
            'offer_active' => $data['active'] ? '1' : '0',
        ];
        [$old, $new] = Audit::diff((array) $item, $row);
        DB::table('catalog_items')->where('id', $id)->update($row);
        $furniChanged = false;

        if (isset($data['furni']) && ctype_digit((string) $item->item_id)) {
            $furni = DB::table('furniture')->where('id', (int) $item->item_id)->first(['interaction_type', 'interaction_modes_count', 'vending_ids', 'allow_trade', 'allow_inventory_stack']);

            if ($furni !== null) {
                $furniRow = [
                    'interaction_type' => $data['furni']['interaction'],
                    'interaction_modes_count' => (int) $data['furni']['modes'],
                    'vending_ids' => (string) ($data['furni']['vending_ids'] ?? '') !== '' ? (string) $data['furni']['vending_ids'] : '0',
                    'allow_trade' => $data['furni']['trade'] ? '1' : '0',
                    'allow_inventory_stack' => $data['furni']['stack'] ? '1' : '0',
                ];
                [$fOld, $fNew] = Audit::diff((array) $furni, $furniRow);

                if ($fNew !== []) {
                    DB::table('furniture')->where('id', (int) $item->item_id)->update($furniRow);
                    $furniChanged = true;
                    $old += array_combine(array_map(fn (string $k): string => 'furniture.' . $k, array_keys($fOld)), $fOld);
                    $new += array_combine(array_map(fn (string $k): string => 'furniture.' . $k, array_keys($fNew)), $fNew);
                }
            }
        }

        $reload = Hotel::reload('catalog');

        if ($furniChanged) {
            $items = Hotel::reload('items');
            $reload = new HotelResult('reloadcatalog + reloaditems', $reload->sent && $items->sent, $reload->sent ? $items->message : $reload->message);
        }

        if ($new !== []) {
            Audit::record('catalog', sprintf('changed the catalog item %s (#%d)', $data['name'], $id), target: ['type' => 'catalog_item', 'id' => $id, 'label' => $data['name']], before: $old, after: $new, rcon: $reload);
        }

        return $this->saved('Item saved', $reload);
    }

    public function reload(): JsonResponse
    {
        $reload = Hotel::reload('catalog');
        Audit::record('catalog', 'reloaded the catalog', rcon: $reload);

        return $this->saved('Catalog reloaded', $reload);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePage(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'caption' => ['required', 'string', 'max:35'],
            'parent' => ['required', 'integer', 'min:-1'],
            'min_rank' => ['required', 'integer', 'min:1', 'max:20'],
            'order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'visible' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ]);
        abort_if($id !== null && (int) $data['parent'] === $id, 422, 'A page cannot sit under itself.');
        abort_if((int) $data['parent'] > 0 && DB::table('catalog_pages')->where('id', $data['parent'])->doesntExist(), 422, 'That parent page does not exist.');

        $row = [
            'caption' => (string) $data['caption'],
            'parent_id' => (int) $data['parent'],
            'min_rank' => (int) $data['min_rank'],
            'visible' => $data['visible'] ? 1 : 0,
            'enabled' => $data['enabled'] ? 1 : 0,
        ];

        if (isset($data['order'])) {
            $row['order_num'] = (int) $data['order'];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function saved(string $message, HotelResult $reload, array $extra = []): JsonResponse
    {
        return response()->json($extra + [
            'message' => $reload->sent ? $message . ' · the hotel reloaded it' : $message . ' · the hotel could not be reached, so it applies on the next reload',
            'reloaded' => $reload->sent,
        ]);
    }

    /**
     * BIT(1) columns arrive as a byte string ("\x01") or an int, by driver.
     */
    private static function bit(mixed $value): bool
    {
        return is_string($value) ? $value === "\x01" || $value === '1' : (bool) $value;
    }
}
