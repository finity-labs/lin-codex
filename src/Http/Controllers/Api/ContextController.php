<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers\Api;

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\PageContext;
use FinityLabs\LinCodex\Http\Controllers\Api\Concerns\ReadsLocaleParameter;
use FinityLabs\LinCodex\Http\Json\ContextPayload;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET {api}/context?route=…&path=…&class=…&panel=…: the articles for the
 * page described by the query string, for the current viewer in the
 * requested locale. The page context is read from the query with
 * PageContext::fromArray(), never detected from this request (the detector
 * would see the API's own route), and ContextResolver has already applied
 * the published, visibility, gate and locale rules. GET only in v1: a POST
 * would need a CSRF token under the web group.
 */
final class ContextController extends Controller
{
    use ReadsLocaleParameter;

    public function __construct(
        private readonly ContextResolver $resolver,
        private readonly ViewerResolver $viewers,
        private readonly LocaleResolver $locales,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $locale = $this->localeParameter($request);
        $viewer = $this->viewers->resolve();
        $effective = $this->locales->resolve($locale);
        $default = $this->locales->defaultLocale();

        $page = PageContext::fromArray($request->query());

        return response()->json(ContextPayload::make(
            $this->resolver->resolve($page, $viewer, $locale),
            $page,
            $effective,
            $default,
            $this->locales,
        ));
    }
}
