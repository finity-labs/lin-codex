<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Sync;

/**
 * What an import or export did, slug by slug and locale by locale, plus the
 * warnings the source raised on the way. This is a collector, not a value
 * object: the importer and exporter append to it while they run and the
 * commands read it once they are done. A slug lands in exactly one of
 * created, updated, skipped or failed per locale.
 */
final class SyncReport
{
    /**
     * @var array<string, array{created: list<string>, updated: list<string>, skipped: list<string>, failed: array<string, string>}>
     */
    private array $locales = [];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    public function created(string $locale, string $slug): void
    {
        $this->bucket($locale);
        $this->locales[$locale]['created'][] = $slug;
    }

    public function updated(string $locale, string $slug): void
    {
        $this->bucket($locale);
        $this->locales[$locale]['updated'][] = $slug;
    }

    public function skipped(string $locale, string $slug): void
    {
        $this->bucket($locale);
        $this->locales[$locale]['skipped'][] = $slug;
    }

    public function failed(string $locale, string $slug, string $reason): void
    {
        $this->bucket($locale);
        $this->locales[$locale]['failed'][$slug] = $reason;
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return list<string> every locale something happened in, sorted
     */
    public function locales(): array
    {
        $locales = array_keys($this->locales);
        sort($locales);

        return $locales;
    }

    /**
     * @param  'created'|'updated'|'skipped'|'failed'  $kind
     */
    public function count(string $locale, string $kind): int
    {
        return count($this->locales[$locale][$kind] ?? []);
    }

    /**
     * @return list<string> every skipped slug once, sorted
     */
    public function skippedSlugs(): array
    {
        $slugs = [];

        foreach ($this->locales as $locale) {
            foreach ($locale['skipped'] as $slug) {
                $slugs[$slug] = true;
            }
        }

        $slugs = array_keys($slugs);
        sort($slugs);

        return $slugs;
    }

    /**
     * @return array<string, string> "{locale}:{slug}" => reason, locales sorted
     */
    public function failures(): array
    {
        $failures = [];

        foreach ($this->locales() as $locale) {
            foreach ($this->locales[$locale]['failed'] as $slug => $reason) {
                $failures[$locale.':'.$slug] = $reason;
            }
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasFailures(): bool
    {
        foreach ($this->locales as $locale) {
            if ($locale['failed'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{locales: array<string, array{created: list<string>, updated: list<string>, skipped: list<string>, failed: array<string, string>}>, warnings: list<string>}
     */
    public function toArray(): array
    {
        $locales = $this->locales;
        ksort($locales);

        return ['locales' => $locales, 'warnings' => $this->warnings];
    }

    private function bucket(string $locale): void
    {
        $this->locales[$locale] ??= ['created' => [], 'updated' => [], 'skipped' => [], 'failed' => []];
    }
}
