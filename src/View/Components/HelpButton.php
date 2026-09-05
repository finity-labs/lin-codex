<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\View\Components;

use FinityLabs\LinCodex\View\PageHelpResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-lin-codex::help-button />: the anchor that opens the drawer.
 *
 * The button owns no state. It dispatches the "codex:open" window event on
 * click and the drawer does the rest; without JavaScript the anchor simply
 * navigates to the help center. The badge shows how many articles the
 * current page has, taken from the same request-scoped PageHelpResolver
 * the drawer's mount() reads, so the count is server-rendered, visible
 * without JavaScript and always equal to the drawer's page list. A host
 * may override the count, hide the badge, or pass the page class, panel
 * id and guard it also gives the drawer.
 */
final class HelpButton extends Component
{
    public function __construct(
        private readonly PageHelpResolver $resolver,
        public ?string $label = null,
        public bool $floating = false,
        public bool $badge = true,
        public ?int $count = null,
        public ?string $pageClass = null,
        public ?string $panelId = null,
        public ?string $guard = null,
    ) {}

    /**
     * The number the badge shows; zero hides it.
     */
    public function badgeCount(): int
    {
        if (! $this->badge) {
            return 0;
        }

        return $this->count ?? $this->resolver->for($this->pageClass, $this->panelId, null, $this->guard)->count();
    }

    public function render(): View
    {
        return view('lin-codex::components.help-button', ['badgeValue' => $this->badgeCount()]);
    }
}
