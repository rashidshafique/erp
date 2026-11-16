{{-- Styles of the core box cavity map cells, shared by the map and the form's live preview. --}}
@once
<style>
    .cb-map { display: flex; flex-direction: column; gap: 10px; }
    .cb-map-cells { display: flex; flex-wrap: wrap; gap: 8px; }
    .cb-cell { width: 76px; min-height: 54px; box-sizing: border-box; padding: 5px 7px; border: 1px solid #b9bdc2; border-radius: 6px; display: flex; flex-direction: column; gap: 1px; text-align: left; font: inherit; color: #1c1d1f; }
    .cb-cell .n { font-weight: 600; font-size: 12px; color: #55585d; }
    .cb-cell .c { font-size: 11px; line-height: 1.25; overflow-wrap: anywhere; }
    .cb-cell.blocked { background: repeating-linear-gradient(45deg, #eceef1 0 6px, #f8f8f9 6px 12px); }
    .cb-cell.blocked .c { color: #55585d; }
    button.cb-cell { cursor: pointer; }
    button.cb-cell:hover, button.cb-cell:focus-visible { border-color: var(--accent, #2f5fd0); outline: 2px solid color-mix(in srgb, var(--accent, #2f5fd0) 30%, transparent); }
    .cb-map-key { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; }
    .cb-map-key span { display: inline-flex; align-items: center; gap: 6px; }
    .cb-map-key i { display: inline-block; flex: none; width: 12px; height: 12px; border-radius: 3px; border: 1px solid #b9bdc2; }
    .cb-map.small .cb-cell { width: 60px; min-height: 44px; padding: 4px 6px; }
</style>
@endonce
