<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers\Api;

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Http\Controllers\Api\Concerns\ReadsLocaleParameter;
use FinityLabs\LinCodex\Http\Json\TreePayload;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use FinityLabs\LinCodex\Reading\TreeBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET {api}/tree: the article tree the current viewer sees in the requested
 * locale. TreeBuilder has already applied the published, visibility and
 * locale rules, so the controller only resolves the viewer and the locale
 * and maps the nodes.
 */
final class TreeController extends Controller
{
    use ReadsLocaleParameter;

    public function __construct(
        private readonly TreeBuilder $tree,
        private readonly ViewerResolver $viewers,
        private readonly LocaleResolver $locales,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $locale = $this->localeParameter($request);
        $viewer = $this->viewers->resolve();
        $effective = $this->locales->resolve($locale);
        $default = $this->locales->defaultLocale();

        return response()->json(TreePayload::make($this->tree->build($viewer, $locale), $effective, $default));
    }
}
