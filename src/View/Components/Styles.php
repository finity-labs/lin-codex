<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\View\Components;

use FinityLabs\LinCodex\Assets\StylesheetVersion;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-lin-codex::styles />: one stylesheet link for the package CSS.
 *
 * The href prefers a copy published with "vendor:publish --tag=lin-codex-assets"
 * (public/vendor/lin-codex/codex.css, served by the web server) and falls
 * back to the package route. Both carry "?v=" with the package file's hash.
 */
final class Styles extends Component
{
    public function __construct(private readonly StylesheetVersion $version) {}

    public function href(): string
    {
        $published = public_path('vendor/lin-codex/codex.css');
        $version = $this->version->hash();

        return is_file($published)
            ? asset('vendor/lin-codex/codex.css').'?v='.$version
            : route('lin-codex.assets.css', ['v' => $version]);
    }

    public function render(): View
    {
        return view('lin-codex::components.styles', ['href' => $this->href()]);
    }
}
