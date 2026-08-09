<?php

namespace App\Services\Deploy;

class ChangelogParser
{
    /**
     * Extract the newest release section from the player-facing CHANGELOG.md.
     *
     * Format (see the maintainer comment at the top of that file):
     * `## YYYY-MM-DD — Title` headings, newest first, each holding
     * `### Added / Changed / Fixed / Known issues` groups of `- ` entries
     * wrapped with two-space hanging indents. Repeated category headings
     * (an easy authoring slip) are merged so consumers can key on category.
     *
     * @return array{date: string, title: string, sections: list<array{category: string, entries: list<string>}>}|null
     */
    public function latestRelease(string $markdown): ?array
    {
        // The maintainer rules live in an HTML comment before the first release.
        $markdown = (string) preg_replace('/<!--.*?-->/s', '', $markdown);

        $lines = preg_split('/\R/u', $markdown);
        if ($lines === false) {
            return null;
        }

        $date = null;
        $title = null;
        $category = null;
        /** @var array<string, list<string>> $entriesByCategory */
        $entriesByCategory = [];
        /** @var list<string> $categoryOrder */
        $categoryOrder = [];

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(\d{4}-\d{2}-\d{2})\s+—\s+(.+)$/u', $line, $matches) === 1) {
                if ($date !== null) {
                    break; // reached the previous (older) release
                }
                $date = $matches[1];
                $title = trim($matches[2]);

                continue;
            }

            if ($date === null) {
                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $line, $matches) === 1) {
                $category = trim($matches[1]);
                if (! isset($entriesByCategory[$category])) {
                    $entriesByCategory[$category] = [];
                    $categoryOrder[] = $category;
                }

                continue;
            }

            if ($category === null) {
                continue;
            }

            if (preg_match('/^-\s+(.*)$/u', $line, $matches) === 1) {
                $entriesByCategory[$category][] = trim($matches[1]);

                continue;
            }

            // Two-space hanging indent — continuation of the previous entry.
            $count = count($entriesByCategory[$category]);
            if ($count > 0 && preg_match('/^\s{2,}(\S.*)$/u', $line, $matches) === 1) {
                $entriesByCategory[$category][$count - 1] .= ' ' . trim($matches[1]);
            }
        }

        $sections = [];
        foreach ($categoryOrder as $name) {
            if ($entriesByCategory[$name] !== []) {
                $sections[] = ['category' => $name, 'entries' => $entriesByCategory[$name]];
            }
        }

        if ($date === null || $title === null || $sections === []) {
            return null;
        }

        return ['date' => $date, 'title' => $title, 'sections' => $sections];
    }
}
