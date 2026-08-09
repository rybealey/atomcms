<?php

use App\Services\Deploy\ChangelogParser;

const SAMPLE_CHANGELOG = <<<'MD'
# PixelRP — Hotel Updates

What's changed in the hotel, written for players. Newest first.

<!--
For maintainers: this file is player-facing.

## 2020-01-01 — A heading inside the comment must be ignored
-->

## 2026-08-09 — The hotel found its tannoy

### Added

- **Hotel-wide announcements.** Staff can now broadcast a message to everyone
  online. It arrives as the small dark notification bubble in the corner.
- **Personal alerts from the team.** Staff can now send a notice to a single
  player.

### Fixed

- **The online counter isn't stuck at zero anymore.** The count is now real.

## 2026-08-09 — The emoji face calmed down

### Fixed

- **The chat bar's emoji face no longer strobes in Safari.**
MD;

test('the newest release is parsed with categories and joined continuations', function () {
    $release = (new ChangelogParser)->latestRelease(SAMPLE_CHANGELOG);

    expect($release)->not->toBeNull()
        ->and($release['date'])->toBe('2026-08-09')
        ->and($release['title'])->toBe('The hotel found its tannoy')
        ->and($release['sections'])->toHaveCount(2)
        ->and($release['sections'][0]['category'])->toBe('Added')
        ->and($release['sections'][0]['entries'][0])->toBe(
            '**Hotel-wide announcements.** Staff can now broadcast a message to everyone online. It arrives as the small dark notification bubble in the corner.',
        )
        ->and($release['sections'][0]['entries'])->toHaveCount(2)
        ->and($release['sections'][1]['category'])->toBe('Fixed')
        ->and($release['sections'][1]['entries'])->toHaveCount(1);
});

test('older releases with the same date are not merged in', function () {
    $release = (new ChangelogParser)->latestRelease(SAMPLE_CHANGELOG);

    $allEntries = collect($release['sections'])->flatMap(fn (array $section) => $section['entries']);

    expect($allEntries->filter(fn (string $entry) => str_contains($entry, 'emoji')))->toBeEmpty();
});

test('a changelog without releases parses to null', function () {
    expect((new ChangelogParser)->latestRelease("# Title\n\nJust prose."))->toBeNull();
});

test('repeated category headings within a release are merged', function () {
    $release = (new ChangelogParser)->latestRelease(<<<'MD'
## 2026-08-09 — Twice fixed

### Fixed

- **First fix.**

### Fixed

- **Second fix.**
MD);

    expect($release['sections'])->toHaveCount(1)
        ->and($release['sections'][0]['category'])->toBe('Fixed')
        ->and($release['sections'][0]['entries'])->toBe(['**First fix.**', '**Second fix.**']);
});
