<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers;

use FinityLabs\LinCodex\Auth\ArticleGate;
use FinityLabs\LinCodex\Auth\ViewerResolver;
use FinityLabs\LinCodex\Contracts\ContentSource;
use FinityLabs\LinCodex\Data\ArticleData;
use FinityLabs\LinCodex\Reading\MediaReferences;
use FinityLabs\LinCodex\Sources\Filesystem\ImagePathRewriter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams the images file articles reference ("{media}/{locale}/{path}")
 * from the configured docs paths.
 *
 * The extension allowlist is checked before any disk access, so a request
 * for ".env" or "01-intro.md" never reaches realpath(). realpath() resolves
 * ".." and symlinks, so the containment check compares canonical paths and
 * refuses anything that lands outside a docs path, in plain or URL-encoded
 * spelling. Later paths win, the same rule articles follow. Laravel only
 * calls isNotModified() inside the SetCacheHeaders middleware, so the
 * controller calls it itself to answer a conditional GET with 304.
 *
 * An image is served when no article references it (a shared asset) or when
 * at least one referencing article is visible to the current viewer. A
 * hidden owner is a 404, the same answer as a missing file, so the route
 * never confirms that a restricted article exists. The locale rule does not
 * apply to images: one file serves every language. The gate runs after the
 * extension check and locate(), so nothing here touches the source for a
 * non-image or a missing file.
 */
final class MediaController extends Controller
{
    /**
     * Extension to MIME type. SVG is deliberately absent: inline SVG can
     * carry scripts.
     *
     * @var array<string, string>
     */
    private const MIME = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function __construct(
        private readonly ContentSource $source,
        private readonly ViewerResolver $viewers,
        private readonly ArticleGate $gate,
    ) {}

    public function __invoke(Request $request, string $locale, string $path): Response
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! isset(self::MIME[$extension])) {
            abort(404);
        }

        $file = $this->locate($locale, $path) ?? abort(404);

        /*
         * The reference key is the lexically normalised "{locale}/{path}" the
         * rewriter would have emitted, so "de/../en/images/x.png" is gated
         * as "en/images/x.png".
         */
        $key = ImagePathRewriter::resolve('', $locale.'/'.$path) ?? abort(404);
        $articles = $this->source->all();
        $owners = MediaReferences::fromArticles($articles, (string) config('lin-codex.routes.media', '/codex/media'))->owners($key);

        if ($owners !== [] && ! $this->anyOwnerVisible($owners, $articles)) {
            abort(404);
        }

        $response = response()->file($file, [
            'Content-Type' => self::MIME[$extension],
            'Cache-Control' => 'public, max-age=86400',
        ])->setAutoEtag();

        $response->isNotModified($request);

        return $response;
    }

    /**
     * @param  list<string>  $owners  slugs referencing the image
     * @param  array<string, ArticleData>  $articles  the source's all() map
     */
    private function anyOwnerVisible(array $owners, array $articles): bool
    {
        $viewer = $this->viewers->resolve();

        foreach ($owners as $slug) {
            if (isset($articles[$slug]) && $this->gate->allows($articles[$slug], $viewer, $articles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The canonical path of the image inside the last configured docs path
     * that holds it, or null when no path holds it or it is outside.
     */
    private function locate(string $locale, string $path): ?string
    {
        $paths = config('lin-codex.sources.filesystem.paths', []);
        $paths = is_array($paths) ? array_values(array_filter($paths, 'is_string')) : [];

        foreach (array_reverse($paths) as $docsPath) {
            $root = realpath($docsPath);

            if ($root === false) {
                continue;
            }

            $candidate = realpath($root.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

            if ($candidate === false || ! is_file($candidate) || ! str_starts_with($candidate, $root.DIRECTORY_SEPARATOR)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}
