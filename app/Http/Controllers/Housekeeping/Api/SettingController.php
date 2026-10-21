<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Services\SettingsService;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Website settings for the panel. Secrets never leave the server: their
 * values come back null with a has_value flag, and a null in a save request
 * means "leave it alone". Changes go to the staff audit trail; the history
 * also reads the log the earlier panel wrote.
 */
class SettingController extends Controller
{
    /** The log the earlier panel wrote settings changes to. */
    private const OLD_LOG = 'housekeeping-settings';

    /** @var list<string> */
    private const SECRET_KEYS = ['ipdata_api_key', 'tinymce_api_key', 'discord_webhook_url'];

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1', 'max:200'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var array<string, string|null> $incoming */
        $incoming = $validated['settings'];

        $existing = WebsiteSetting::query()->whereIn('key', array_keys($incoming))->get()->keyBy('key');
        $changed = [];

        foreach ($incoming as $key => $value) {
            /** @var WebsiteSetting|null $setting */
            $setting = $existing->get($key);

            if ($setting === null || $value === null || (string) $setting->value === $value) {
                continue;
            }

            $secret = in_array($key, self::SECRET_KEYS, true);
            $old = $setting->value;
            $setting->value = $value;
            $setting->save();
            $changed[] = $key;

            Audit::record(
                'setting',
                sprintf('changed the website setting %s', $key),
                before: ['value' => $secret ? '••••' : $old],
                after: ['value' => $secret ? '••••' : $value],
                reason: $validated['reason'] ?? null,
                target: ['type' => 'website_setting', 'id' => $key],
                subject: $setting,
            );
        }

        SettingsService::clearCache();

        return response()->json([
            'changed' => $changed,
            'settings' => $this->all(),
        ]);
    }

    public function changes(): JsonResponse
    {
        $entries = Activity::query()
            ->where(fn ($q) => $q->where('log_name', self::OLD_LOG)->orWhere(fn ($n) => $n->where('log_name', Audit::LOG)->where('event', 'setting')))
            ->latest('id')
            ->limit(40)
            ->get();
        $refs = PlayerRefs::many($entries->pluck('causer_id'));

        return response()->json(['changes' => $entries->map(function (Activity $entry) use ($refs): array {
            /** @var array<string, mixed> $p */
            $p = $entry->properties?->toArray() ?? [];
            $key = (string) ($p['key'] ?? ($p['target']['id'] ?? ''));

            return [
                'id' => (int) $entry->id,
                'actor' => PlayerRefs::pick($refs, $entry->causer_id),
                'key' => $key,
                'old' => isset($p['old']) ? (string) $p['old'] : (isset($p['before']['value']) ? (string) $p['before']['value'] : null),
                'new' => isset($p['new']) ? (string) $p['new'] : (isset($p['after']['value']) ? (string) $p['after']['value'] : null),
                'reason' => is_string($p['reason'] ?? null) ? $p['reason'] : null,
                'at' => Epoch::of($entry->created_at),
            ];
        })->values()]);
    }

    /**
     * @return list<array{key: string, value: string|null, comment: string, secret: bool, has_value: bool}>
     */
    private function all(): array
    {
        return array_values(WebsiteSetting::query()
            ->orderBy('key')
            ->get()
            ->map(function (WebsiteSetting $setting): array {
                $secret = in_array($setting->key, self::SECRET_KEYS, true);

                return [
                    'key' => $setting->key,
                    'value' => $secret ? null : (string) $setting->value,
                    'comment' => (string) $setting->comment,
                    'secret' => $secret,
                    'has_value' => (string) $setting->value !== '',
                ];
            })
            ->all());
    }
}
