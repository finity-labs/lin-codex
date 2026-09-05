<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Contexts;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Reduces a request to a PageContext. Called once, at component mount, with
 * the request passed in; hosts that know more (a Filament resource class, a
 * panel id) pass it along. Nothing else in the package reads the request.
 *
 * The decoded path is used because the router matched on the decoded path
 * too; the PageContext constructor normalises it. The route name is only
 * available once a router has matched, so a request built outside the
 * router yields no route name.
 */
final class RequestContextDetector
{
    public function detect(Request $request, ?string $pageClass = null, ?string $panelId = null): PageContext
    {
        $route = $request->route();
        $name = $route instanceof Route ? $route->getName() : null;

        return new PageContext($name, $request->decodedPath(), $pageClass, $panelId);
    }
}
