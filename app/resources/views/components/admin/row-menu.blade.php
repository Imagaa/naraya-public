{{--
    "⋯" menu for a list row's secondary actions, on the native popover element:
    light dismiss (outside click / Esc), top layer (never clipped by the list).
    Opens under its button, flipping above when there's no room. Items use
    class="adm-menu-item" (a link or a button); choosing one closes the menu.
--}}
@props(['id', 'label' => 'Aksi lainnya'])

<span class="adm-row-menu" x-data>
    <button type="button" class="adm-icon-btn adm-icon-btn-sm" popovertarget="{{ $id }}" aria-label="{{ $label }}"><x-lucide-ellipsis width="18" height="18" /></button>
    <div id="{{ $id }}" popover class="adm-menu-panel" role="menu"
         x-on:toggle="if ($event.newState !== 'open') return; const r = $el.previousElementSibling.getBoundingClientRect(); const h = $el.offsetHeight; $el.style.top = (r.bottom + h + 8 > innerHeight ? r.top - h - 4 : r.bottom + 4) + 'px'; $el.style.left = Math.max(8, r.right - $el.offsetWidth) + 'px';"
         x-on:click="if ($event.target.closest('.adm-menu-item')) $el.hidePopover()">
        {{ $slot }}
    </div>
</span>
