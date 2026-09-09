<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\Miscellaneous\WebsiteSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Website settings for the panel. Secrets never leave the server: their
 * values come back null with a has_value flag, and a null in a save request
 * means "leave it alone".
 */
class SettingController extends Controller
{
    private const LOG_NAME = 'housekeeping-settings';

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
        ]);

        /** @var User $actor */
        $actor = $request->user();
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

            activity(self::LOG_NAME)
                ->causedBy($actor)
                ->performedOn($setting)
                ->withProperties([
                    'key' => $key,
                    'old' => $secret ? '••••' : $old,
                    'new' => $secret ? '••••' : $value,
                ])
                ->event('updated')
                ->log("Setting {$key} changed");
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
            ->with('causer')
            ->where('log_name', self::LOG_NAME)
            ->latest()
            ->limit(40)
            ->get()
            ->map(function (Activity $entry): array {
                $causer = $entry->causer;
                /** @var array<string, mixed> $properties */
                $properties = collect($entry->properties)->toArray();

                return [
                    'id' => $entry->id,
                    'who' => $causer instanceof User ? $causer->username : 'System',
                    'look' => $causer instanceof User ? (string) $causer->look : null,
                    'key' => (string) ($properties['key'] ?? ''),
                    'old' => isset($properties['old']) ? (string) $properties['old'] : null,
                    'new' => isset($properties['new']) ? (string) $properties['new'] : null,
                    'at' => $entry->created_at?->timestamp,
                ];
            })
            ->values();

        return response()->json(['changes' => $entries]);
    }

    /**
     * @return list<array{key: string, value: string|null, comment: string, secret: bool, has_value: bool}>
     */
    private function all(): array
    {
        return WebsiteSetting::query()
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
            ->values()
            ->all();
    }
}
