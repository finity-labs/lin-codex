<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers\Api;

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Http\Controllers\Api\Concerns\ReadsLocaleParameter;
use FinityLabs\LinCodex\Http\Json\ArticlePayload;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Reading\ArticleReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET {api}/articles/{slug}: one rendered article for the current viewer in
 * the requested locale. ArticleReader has already applied the published,
 * visibility, gate and locale rules and answers null for every refusal, so
 * a missing, hidden, unpublished or gate-vetoed slug is one and the same
 * 404 with a JSON {message} body; nothing answers 403, because that would
 * confirm the article exists.
 */
final class ArticleController extends Controller
{
    use ReadsLocaleParameter;

    public function __construct(
        private readonly ArticleReader $reader,
        private readonly ViewerResolver $viewers,
        private readonly LocaleResolver $locales,
    ) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $locale = $this->localeParameter($request);
        $viewer = $this->viewers->resolve();
        $effective = $this->locales->resolve($locale);
        $default = $this->locales->defaultLocale();

        $read = $this->reader->read($slug, $viewer, $locale);

        if ($read === null) {
            return response()->json(['message' => (string) __('lin-codex::lin-codex.api.not_found')], 404);
        }

        return response()->json(ArticlePayload::make($read, $effective, $default));
    }
}
