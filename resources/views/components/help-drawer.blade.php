{{-- <x-lin-codex::help-drawer /> exists so a host writes one Blade tag next to the help button and fin-codex passes the resource class and the panel id the same way. The Livewire tag is self-closed: Livewire 4 requires it. Livewire copies every tag parameter onto a public property of the same name before mount(), and the drawer's locked $locale is a non-nullable string, so the locale is only passed when the host set one. --}}
@props(['slug' => null, 'pageClass' => null, 'panelId' => null, 'locale' => null])
@if ($locale === null)
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" />
@else
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" :locale="$locale" />
@endif
