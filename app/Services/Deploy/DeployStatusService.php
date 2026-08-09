<?php

namespace App\Services\Deploy;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live status of the "Deploy to VPS" GitHub Actions run, shaped for the
 * in-client deployment screen. All GitHub traffic happens server-side:
 * the computed payload is cached briefly, the runs list is revalidated with
 * ETags (304s don't count against the rate limit), so any number of
 * disconnected players polling stays within the unauthenticated budget.
 */
class DeployStatusService
{
    /**
     * Workflow step name → the deployment screen's step text. Steps not
     * listed here (checkout, client build, SSH setup) display as the first
     * phase — players only ever see the screen after the emulator stops.
     */
    private const STEP_TEXT = [
        'Backing up city data' => 'Backing up city data…',
        'Uploading new assets' => 'Uploading new assets…',
        'Applying database patches' => 'Applying database patches…',
        'Restarting districts' => 'Restarting districts…',
        'Polishing pixels' => 'Polishing pixels…',
    ];

    private const ACTIVE_STATUSES = ['queued', 'in_progress', 'waiting', 'requested', 'pending'];

    /** Used until enough successful runs exist to average over. */
    private const FALLBACK_DURATION_SECONDS = 300;

    /** How long a finished run keeps reporting done/failed to late pollers. */
    private const COMPLETED_WINDOW_SECONDS = 180;

    private const PAYLOAD_CACHE_SECONDS = 5;

    private const RUNS_REVALIDATE_SECONDS = 10;

    public function __construct(private readonly ChangelogParser $changelogParser) {}

    /**
     * @return array{status: string, progress: int, step: string|null, etaSeconds: int|null, changelog: array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null}
     */
    public function status(): array
    {
        /** @var array{status: string, progress: int, step: string|null, etaSeconds: int|null, changelog: array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null} */
        return Cache::remember(
            'deploy_status:payload',
            self::PAYLOAD_CACHE_SECONDS,
            fn (): array => $this->compute(),
        );
    }

    /**
     * @return array{status: string, progress: int, step: string|null, etaSeconds: int|null, changelog: array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null}
     */
    private function compute(): array
    {
        $runs = $this->fetchRuns();
        $latest = $runs[0] ?? null;

        if ($latest === null) {
            return $this->payload('idle');
        }

        $runStatus = is_string($latest['status'] ?? null) ? $latest['status'] : '';

        if (in_array($runStatus, self::ACTIVE_STATUSES, true)) {
            $startedRaw = $latest['run_started_at'] ?? $latest['created_at'] ?? null;
            $started = is_string($startedRaw) ? CarbonImmutable::parse($startedRaw) : CarbonImmutable::now();
            $elapsed = max(0, (int) $started->diffInSeconds(CarbonImmutable::now()));
            $expected = $this->expectedDuration($runs);

            $runId = is_numeric($latest['id'] ?? null) ? (int) $latest['id'] : null;

            return $this->payload(
                'deploying',
                progress: (int) min(97, max(3, round($elapsed / $expected * 100))),
                step: $runId === null ? self::STEP_TEXT['Backing up city data'] : $this->currentStepText($runId),
                etaSeconds: max(30, $expected - $elapsed),
            );
        }

        $finishedRaw = $latest['updated_at'] ?? null;
        $finished = is_string($finishedRaw) ? CarbonImmutable::parse($finishedRaw) : null;

        if ($finished !== null && $finished->diffInSeconds(CarbonImmutable::now()) <= self::COMPLETED_WINDOW_SECONDS) {
            return ($latest['conclusion'] ?? null) === 'success'
                ? $this->payload('done', progress: 100)
                : $this->payload('failed');
        }

        return $this->payload('idle');
    }

    /**
     * @return array{status: string, progress: int, step: string|null, etaSeconds: int|null, changelog: array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null}
     */
    private function payload(string $status, int $progress = 0, ?string $step = null, ?int $etaSeconds = null): array
    {
        return [
            'status' => $status,
            'progress' => $progress,
            'step' => $step,
            'etaSeconds' => $etaSeconds,
            'changelog' => $this->changelog(),
        ];
    }

    /**
     * The workflow's runs, newest first — ETag-revalidated so idle polling is
     * effectively free against the GitHub rate limit.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRuns(): array
    {
        /** @var array{etag: string|null, runs: list<array<string, mixed>>, fetched_at: int}|null $cached */
        $cached = Cache::get('deploy_status:runs');

        if ($cached !== null && (time() - $cached['fetched_at']) < self::RUNS_REVALIDATE_SECONDS) {
            return $cached['runs'];
        }

        $repo = (string) config('services.github_deploy.repo');
        $workflow = (string) config('services.github_deploy.workflow');

        $request = Http::acceptJson()->timeout(5);

        $token = config('services.github_deploy.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        if ($cached !== null && $cached['etag'] !== null) {
            $request = $request->withHeaders(['If-None-Match' => $cached['etag']]);
        }

        try {
            $response = $request->get(
                "https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/runs",
                ['per_page' => 10],
            );
        } catch (Throwable) {
            return $cached['runs'] ?? [];
        }

        if ($response->status() === 304 && $cached !== null) {
            Cache::put('deploy_status:runs', [...$cached, 'fetched_at' => time()], now()->addHour());

            return $cached['runs'];
        }

        if (! $response->successful()) {
            return $cached['runs'] ?? [];
        }

        $runs = $response->json('workflow_runs');
        $runs = is_array($runs) ? array_values(array_filter($runs, 'is_array')) : [];

        Cache::put('deploy_status:runs', [
            'etag' => $response->header('ETag') ?: null,
            'runs' => $runs,
            'fetched_at' => time(),
        ], now()->addHour());

        return $runs;
    }

    private function currentStepText(int $runId): string
    {
        $repo = (string) config('services.github_deploy.repo');

        $request = Http::acceptJson()->timeout(5);

        $token = config('services.github_deploy.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get("https://api.github.com/repos/{$repo}/actions/runs/{$runId}/jobs");
        } catch (Throwable) {
            return self::STEP_TEXT['Backing up city data'];
        }

        $jobs = $response->json('jobs');
        $current = null;

        foreach (is_array($jobs) ? $jobs : [] as $job) {
            if (! is_array($job) || ! is_array($job['steps'] ?? null)) {
                continue;
            }
            foreach ($job['steps'] as $step) {
                if (! is_array($step) || ! is_string($step['name'] ?? null)) {
                    continue;
                }
                if (($step['status'] ?? null) === 'in_progress') {
                    $current = $step['name'];
                    break 2;
                }
                if (($step['status'] ?? null) === 'completed') {
                    $current = $step['name']; // remember the furthest finished step
                }
            }
        }

        if ($current === null) {
            return self::STEP_TEXT['Backing up city data'];
        }

        return self::STEP_TEXT[$current] ?? self::STEP_TEXT['Backing up city data'];
    }

    /**
     * Average duration of recent successful runs — the basis for the
     * progress bar and the "est. ~N min" the screen shows.
     *
     * @param  list<array<string, mixed>>  $runs
     */
    private function expectedDuration(array $runs): int
    {
        $durations = [];

        foreach ($runs as $run) {
            if (($run['status'] ?? null) !== 'completed' || ($run['conclusion'] ?? null) !== 'success') {
                continue;
            }
            if (! is_string($run['run_started_at'] ?? null) || ! is_string($run['updated_at'] ?? null)) {
                continue;
            }
            $seconds = (int) CarbonImmutable::parse($run['run_started_at'])
                ->diffInSeconds(CarbonImmutable::parse($run['updated_at']));
            if ($seconds > 30) {
                $durations[] = $seconds;
            }
            if (count($durations) === 5) {
                break;
            }
        }

        return $durations === []
            ? self::FALLBACK_DURATION_SECONDS
            : (int) round(array_sum($durations) / count($durations));
    }

    /**
     * @return array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null
     */
    private function changelog(): ?array
    {
        /** @var array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null */
        return Cache::remember('deploy_status:changelog', 60, function (): ?array {
            $configured = config('services.github_deploy.changelog_path');
            $path = is_string($configured) && $configured !== '' ? $configured : base_path('CHANGELOG.md');

            $markdown = is_file($path) ? (string) file_get_contents($path) : $this->fetchRemoteChangelog();

            return $markdown === '' ? null : $this->changelogParser->latestRelease($markdown);
        });
    }

    private function fetchRemoteChangelog(): string
    {
        $repo = (string) config('services.github_deploy.repo');

        try {
            $response = Http::timeout(5)->get("https://raw.githubusercontent.com/{$repo}/main/CHANGELOG.md");
        } catch (Throwable) {
            return '';
        }

        return $response->successful() ? $response->body() : '';
    }
}
