<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use stdClass;

/**
 * Emulator settings: PlusEMU's server_settings. A change is saved, then the
 * emulator re-reads the table over RCON (reload_server_settings). The world
 * switches the City Panel owns are shown read-only: the City Panel changes
 * them and tells everyone online.
 */
class EmulatorSettingController extends Controller
{
    /** Keys the City Panel owns (CityWorld, WeatherStation). */
    private const IN_GAME = ['hotel.maintenance', 'hotel.combat_paused', 'city.weather.pinned', 'city.weather.code', 'city.time.pinned', 'city.time.minutes'];

    public function index(): JsonResponse
    {
        $table = self::table();
        $rows = DB::table($table)->orderBy('key')->get();
        $changes = Activity::query()->where('log_name', Audit::LOG)->where('event', 'emulator_setting')->latest('id')->limit(20)->get();
        $refs = PlayerRefs::many($changes->pluck('causer_id'));

        return response()->json([
            'table' => $table,
            'settings' => $rows->map(fn (stdClass $r): array => [
                'key' => (string) $r->key,
                'value' => (string) ($r->value ?? ''),
                'description' => (string) ($r->description ?? $r->comment ?? ''),
                'group' => self::group((string) $r->key),
                'path' => in_array((string) $r->key, self::IN_GAME, true) ? 'game' : 'saved',
            ])->values(),
            'changes' => $changes->map(fn (Activity $a): array => [
                'id' => (int) $a->id,
                'at' => Epoch::of($a->created_at),
                'actor' => PlayerRefs::pick($refs, $a->causer_id),
                'summary' => lcfirst((string) $a->description),
                'before' => data_get($a->properties, 'before.value'),
                'after' => data_get($a->properties, 'after.value'),
                'reason' => data_get($a->properties, 'reason'),
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array', 'min:1', 'max:100'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $table = self::table();
        $existing = DB::table($table)->whereIn('key', array_keys($data['settings']))->pluck('value', 'key');
        $changed = 0;

        foreach ($data['settings'] as $key => $value) {
            $key = (string) $key;

            if ($value === null || ! $existing->has($key) || in_array($key, self::IN_GAME, true) || (string) $existing->get($key) === $value) {
                continue;
            }

            DB::table($table)->where('key', $key)->update(['value' => $value]);
            Audit::record('emulator_setting', sprintf('set %s to %s', $key, $value), before: ['value' => (string) $existing->get($key)], after: ['value' => $value], reason: $data['reason'] ?? null, target: ['type' => 'server_setting', 'id' => $key]);
            $changed++;
        }

        $reload = $changed > 0 ? Hotel::reload('settings') : null;

        return response()->json([
            'changed' => $changed,
            'reloaded' => $reload?->sent,
            'message' => $changed === 0 ? 'Nothing changed' : sprintf('%d %s saved', $changed, $changed === 1 ? 'setting' : 'settings') . ($reload !== null ? ($reload->sent ? '; the hotel reloaded them' : '; the hotel could not be reached, so they apply at the next restart') : ''),
        ]);
    }

    private static function table(): string
    {
        return RoleplaySchema::has('server_settings') ? 'server_settings' : 'emulator_settings';
    }

    /**
     * The group a key sits in, by its prefix.
     */
    private static function group(string $key): string
    {
        $prefix = explode('.', $key)[0];

        return match ($prefix) {
            'hotel', 'bot', 'movement' => 'Hotel',
            'city', 'turf', 'gang' => 'City',
            'catalog', 'trading', 'vip', 'user' => 'Economy',
            'room', 'group', 'messenger' => 'Rooms and social',
            default => 'Other',
        };
    }
}
