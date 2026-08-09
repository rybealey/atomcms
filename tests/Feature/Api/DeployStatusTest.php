<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

const RUNS_URL = 'https://api.github.com/repos/rybealey/pixelrp/actions/workflows/deploy.yml/runs*';
const JOBS_URL = 'https://api.github.com/repos/rybealey/pixelrp/actions/runs/*/jobs';

beforeEach(function () {
    installHotel();

    $this->travelTo(CarbonImmutable::parse('2026-08-09T12:00:00Z'));

    $changelog = tempnam(sys_get_temp_dir(), 'changelog');
    file_put_contents($changelog, <<<'MD'
## 2026-08-09 — The hotel found its tannoy

### Added

- **Hotel-wide announcements.** Staff can now broadcast.
MD);
    config()->set('services.github_deploy.changelog_path', $changelog);
});

function successfulRun(string $startedAt, string $updatedAt): array
{
    return [
        'id' => 1000,
        'status' => 'completed',
        'conclusion' => 'success',
        'run_started_at' => $startedAt,
        'updated_at' => $updatedAt,
    ];
}

test('no recent run reports idle with the latest release notes', function () {
    Http::fake([RUNS_URL => Http::response([
        'workflow_runs' => [successfulRun('2026-08-09T09:00:00Z', '2026-08-09T09:04:00Z')],
    ])]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.status', 'idle')
        ->assertJsonPath('data.changelog.date', '2026-08-09')
        ->assertJsonPath('data.changelog.title', 'The hotel found its tannoy')
        ->assertJsonPath('data.changelog.sections.0.category', 'Added');
});

test('an in-progress run reports live progress, step text and eta', function () {
    Http::fake([
        RUNS_URL => Http::response(['workflow_runs' => [
            [
                'id' => 42,
                'status' => 'in_progress',
                'conclusion' => null,
                'run_started_at' => '2026-08-09T11:58:00Z', // 120s ago
                'updated_at' => '2026-08-09T11:58:00Z',
            ],
            // Two successful runs of 240s each drive the estimate.
            successfulRun('2026-08-09T09:00:00Z', '2026-08-09T09:04:00Z'),
            successfulRun('2026-08-08T09:00:00Z', '2026-08-08T09:04:00Z'),
        ]]),
        JOBS_URL => Http::response(['jobs' => [[
            'name' => 'Deploy',
            'status' => 'in_progress',
            'steps' => [
                ['name' => 'Backing up city data', 'status' => 'completed'],
                ['name' => 'Applying database patches', 'status' => 'in_progress'],
                ['name' => 'Restarting districts', 'status' => 'queued'],
            ],
        ]]]),
    ]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.status', 'deploying')
        ->assertJsonPath('data.progress', 50)
        ->assertJsonPath('data.step', 'Applying database patches…')
        ->assertJsonPath('data.etaSeconds', 120);
});

test('runner-side prep steps display as the first player phase', function () {
    Http::fake([
        RUNS_URL => Http::response(['workflow_runs' => [[
            'id' => 43,
            'status' => 'in_progress',
            'conclusion' => null,
            'run_started_at' => '2026-08-09T11:59:30Z',
            'updated_at' => '2026-08-09T11:59:30Z',
        ]]]),
        JOBS_URL => Http::response(['jobs' => [[
            'name' => 'Deploy',
            'status' => 'in_progress',
            'steps' => [['name' => 'Build the Nitro client', 'status' => 'in_progress']],
        ]]]),
    ]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.step', 'Backing up city data…');
});

test('a freshly successful run reports done', function () {
    Http::fake([RUNS_URL => Http::response([
        'workflow_runs' => [successfulRun('2026-08-09T11:55:00Z', '2026-08-09T11:59:00Z')],
    ])]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.progress', 100);
});

test('a freshly failed run reports failed', function () {
    Http::fake([RUNS_URL => Http::response(['workflow_runs' => [[
        'id' => 44,
        'status' => 'completed',
        'conclusion' => 'failure',
        'run_started_at' => '2026-08-09T11:55:00Z',
        'updated_at' => '2026-08-09T11:59:00Z',
    ]]])]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');
});

test('github being unreachable degrades to idle instead of erroring', function () {
    Http::fake([RUNS_URL => Http::response(null, 500)]);

    $this->getJson('/api/deploy-status')
        ->assertOk()
        ->assertJsonPath('data.status', 'idle');
});
