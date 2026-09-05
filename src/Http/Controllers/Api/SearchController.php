<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers\Api;

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Http\Controllers\Api\Concerns\ReadsLocaleParameter;
use FinityLabs\LinCodex\Http\Json\SearchPayload;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Search\Searcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET {api}/search?q=…[&limit=…]: the hits the current viewer may see in the
 * requested locale. Searcher has already applied the published, visibility,
 * gate and locale rules and the rate limiter. A missing or blank q and a
 * non-integer limit answer 422 with a JSON {message}; a throttled viewer
 * answers 429 with the same body shape and a Retry-After header; a query
 * below the minimum length is a 200 with empty data, as the searcher
 * decides, so the controller adds no length check of its own.
 */
final class SearchController extends Controller
{
    use ReadsLocaleParameter;

    public function __construct(
        private readonly Searcher $searcher,
        private readonly ViewerResolver $viewers,
        private readonly LocaleResolver $locales,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = $request->query('q');

        if (! is_string($q) || trim($q) === '') {
            return response()->json(['message' => (string) __('lin-codex::lin-codex.api.missing_query')], 422);
        }

        $raw = $request->query('limit');
        $limit = null;

        if ($raw !== null && $raw !== '') {
            if (! is_string($raw) || filter_var($raw, FILTER_VALIDATE_INT) === false) {
                return response()->json(['message' => (string) __('lin-codex::lin-codex.api.invalid_limit')], 422);
            }

            $limit = (int) $raw;
        }

        $locale = $this->localeParameter($request);
        $viewer = $this->viewers->resolve();
        $effective = $this->locales->resolve($locale);
        $default = $this->locales->defaultLocale();

        $result = $this->searcher->search($q, $viewer, $locale, $limit);

        if ($result->rateLimited) {
            $seconds = $result->retryAfterSeconds ?? 1;

            return response()
                ->json(['message' => (string) __('lin-codex::lin-codex.api.rate_limited', ['seconds' => $seconds])], 429)
                ->header('Retry-After', (string) $seconds);
        }

        return response()->json(SearchPayload::make($result, $this->searcher->effectiveLimit($limit), $effective, $default));
    }
}
