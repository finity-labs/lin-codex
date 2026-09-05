<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Http\Controllers;

use FinityLabs\LinCodex\Assets\StylesheetVersion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the prebuilt stylesheet ("{assets}/codex.css") straight from the
 * package, so a host needs no build step and no publish to style the
 * drawer.
 *
 * The cache headers say "immutable" for a year, which is only correct
 * because the URL the styles component emits carries the file hash: a
 * new package version is a new URL. The ETag is that hash too, and
 * isNotModified() is called here because Laravel only calls it inside the
 * SetCacheHeaders middleware, which this route does not run.
 */
final class StylesheetController extends Controller
{
    public function __invoke(Request $request, StylesheetVersion $version): Response
    {
        $response = response()->file($version->path(), [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ])->setEtag($version->hash());

        $response->isNotModified($request);

        return $response;
    }
}
