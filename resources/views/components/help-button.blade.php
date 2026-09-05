{{-- Rendered by View\Components\HelpButton. Package classes come first so a host class merges after them. $badgeValue is the resolved count (the badgeCount() method itself is also exposed to the view as a closure, so the value travels under its own name). --}}
<a href="{{ route('lin-codex.help-center') }}"
   {{ $attributes->class(['codex-help-button', 'codex-help-button--labelled' => $label !== null, 'codex-help-button--floating' => $floating]) }}
   data-codex-help-button
   x-data
   x-on:click.prevent="window.dispatchEvent(new CustomEvent('codex:open'))"
   aria-label="{{ $label ?? __('lin-codex::lin-codex.ui.help') }}">
    <svg class="codex-help-button__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
    @if ($label !== null)
        <span class="codex-help-button__label">{{ $label }}</span>
    @endif
    @if ($badgeValue > 0)
        <span class="codex-help-button__badge" aria-hidden="true">{{ $badgeValue }}</span>
    @endif
</a>
