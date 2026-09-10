<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Http\Controllers\Api\ArticleController;
use FinityLabs\LinCodex\Http\Controllers\Api\ContextController;
use FinityLabs\LinCodex\Http\Controllers\Api\SearchController;
use FinityLabs\LinCodex\Http\Controllers\Api\TreeController;
use FinityLabs\LinCodex\Http\Controllers\MediaController;
use FinityLabs\LinCodex\Http\Controllers\StylesheetController;
use FinityLabs\LinCodex\Livewire\HelpCenter;
use FinityLabs\LinCodex\Sources\Filesystem\FilePath;
use Illuminate\Support\Facades\Route;

/*
 * The help center prefix is read once, here at the top, because an unusable
 * value must stop the file before a single route is registered. A prefix
 * that trims to nothing would mount the page at the site root, where its
 * "{slug}" route swallows every route declared after it.
 */
$helpCenter = config('lin-codex.routes.help_center');

if ($helpCenter !== null && (! is_string($helpCenter) || rtrim($helpCenter, '/') === '')) {
    throw new InvalidArgumentException(sprintf(
        'lin-codex.routes.help_center must be a URL prefix such as "/help", or null to switch the public help center off; %s would mount it at the site root.',
        var_export($helpCenter, true),
    ));
}

Route::get(rtrim((string) config('lin-codex.routes.media', '/codex/media'), '/').'/{locale}/{path}', MediaController::class)
    ->where(['locale' => FilePath::LOCALE_PATTERN, 'path' => '.+'])
    ->middleware(config('lin-codex.routes.middleware', ['web']))
    ->name('lin-codex.media');

/*
 * The JSON API. It lives in this file on purpose: a routes/api.php would
 * suggest the "api" middleware group, while these endpoints run under the
 * configured group ("web" by default) so the session guard identifies the
 * viewer. The slug pattern is ".+" rather than ".*" so "articles/" never
 * reaches the controller.
 */
Route::prefix(rtrim((string) config('lin-codex.routes.api', '/codex/api'), '/'))
    ->middleware(config('lin-codex.routes.middleware', ['web']))
    ->name('lin-codex.api.')
    ->group(function (): void {
        Route::get('tree', TreeController::class)->name('tree');
        Route::get('articles/{slug}', ArticleController::class)->where('slug', '.+')->name('article');
        Route::get('search', SearchController::class)->name('search');
        Route::get('context', ContextController::class)->name('context');
    });

/*
 * The help center: two routes rather than one optional parameter so both
 * names exist. ArticlePath::href() builds "{help_center}/{slug}", the
 * article route; the slug pattern ".+" lets nested slugs through.
 *
 * A null prefix registers neither route, so a request to /help answers 404
 * like any other unknown URL. The "lin-codex.help-center" Livewire component
 * and the help_center_layout key keep working either way, which is how a
 * host mounts the page on a route of its own.
 */
if ($helpCenter !== null) {
    $helpCenter = rtrim($helpCenter, '/');

    Route::get($helpCenter, HelpCenter::class)
        ->middleware(config('lin-codex.routes.middleware', ['web']))
        ->name('lin-codex.help-center');

    Route::get($helpCenter.'/{slug}', HelpCenter::class)
        ->where('slug', '.+')
        ->middleware(config('lin-codex.routes.middleware', ['web']))
        ->name('lin-codex.help-center.article');
}

/*
 * The prebuilt stylesheet. No middleware group on purpose: session, CSRF
 * and cookies have no business on a static file.
 */
Route::get(rtrim((string) config('lin-codex.routes.assets', '/codex/assets'), '/').'/codex.css', StylesheetController::class)
    ->name('lin-codex.assets.css');
