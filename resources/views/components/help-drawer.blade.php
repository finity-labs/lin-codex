{{-- <x-lin-codex::help-drawer /> exists so a host writes one Blade tag next to the help button and fin-codex passes the resource class, the panel id, the guard, the shortcut and the width the same way. Every Livewire tag below is self-closed (Livewire 4 requires it), and because Livewire copies every tag parameter onto a public property of the same name before mount(), two parameters are only forwarded when the host set them: the locale, whose locked property is a non-nullable string, and the shortcut, whose false default marks "not passed" and would arrive as null (and disable the configured shortcut) if the tag carried it. --}}
@props(['slug' => null, 'pageClass' => null, 'panelId' => null, 'locale' => null, 'guard' => null, 'shortcut' => false, 'width' => null])
@if ($locale === null && $shortcut === false)
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" :guard="$guard" :width="$width" />
@elseif ($locale === null)
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" :guard="$guard" :shortcut="$shortcut" :width="$width" />
@elseif ($shortcut === false)
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" :locale="$locale" :guard="$guard" :width="$width" />
@else
    <livewire:lin-codex.help-drawer :slug="$slug" :page-class="$pageClass" :panel-id="$panelId" :locale="$locale" :guard="$guard" :shortcut="$shortcut" :width="$width" />
@endif
