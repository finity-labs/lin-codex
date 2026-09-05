<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\View;

use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contexts\ContextResolver;
use FinityLabs\LinCodex\Contexts\RequestContextDetector;
use FinityLabs\LinCodex\Locale\LocaleResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Resolves the help for the current request's page once per request and
 * per (page class, panel id, locale) key. The help button and the drawer
 * mount both call for() during the initial page render, so the memo means
 * one detection and one ContextResolver pass per page for the badge count
 * and the drawer's page list together.
 *
 * Bound as a scoped instance. The request is read lazily from the
 * container on every call rather than injected: a scoped instance is not
 * flushed between the in-process requests a test issues, and the memo is
 * only valid for the request object it was built from. When the request
 * instance differs, the memo is dropped.
 *
 * Only the initial page render may call this. A Livewire update request is
 * routed to Livewire's own endpoint, so detecting the page there would
 * describe the wrong route; the components copy what they need into locked
 * properties at mount and never come back here.
 */
final class PageHelpResolver
{
    private ?Request $memoRequest = null;

    /** @var array<string, PageHelp> */
    private array $memo = [];

    public function __construct(
        private readonly Container $app,
        private readonly RequestContextDetector $detector,
        private readonly ContextResolver $resolver,
        private readonly ViewerResolver $viewers,
        private readonly LocaleResolver $locales,
    ) {}

    public function for(?string $pageClass = null, ?string $panelId = null, ?string $locale = null): PageHelp
    {
        $request = $this->currentRequest();

        if ($this->memoRequest !== $request) {
            $this->memoRequest = $request;
            $this->memo = [];
        }

        $locale = $this->locales->resolve($locale);
        $key = $pageClass.'|'.$panelId.'|'.$locale;

        return $this->memo[$key] ??= $this->resolve($request, $pageClass, $panelId, $locale);
    }

    private function resolve(Request $request, ?string $pageClass, ?string $panelId, string $locale): PageHelp
    {
        $context = $this->detector->detect($request, $pageClass, $panelId);
        $viewer = $this->viewers->resolve();
        $articles = [];

        foreach ($this->resolver->resolve($context, $viewer, $locale) as $article) {
            $choice = $this->locales->pick($article, $locale);

            if ($choice === null) {
                continue;
            }

            $articles[] = [
                'slug' => $article->slug,
                'title' => $choice->translation->title,
                'excerpt' => $choice->translation->excerpt,
                'isFallback' => $choice->isFallback,
            ];
        }

        return new PageHelp($context, $locale, $articles);
    }

    private function currentRequest(): Request
    {
        /** @var Request $request */
        $request = $this->app->make('request');

        return $request;
    }
}
