{{-- Facts grid and section titles of the core and core box pages (designs 29b, 29e): small grey label over the value, four to a row, no boxes. --}}
@once
<style>
    .tool-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px 24px; margin: 0; }
    .tool-facts > div { min-width: 0; }
    .tool-facts dt { margin: 0 0 3px; font-size: 12px; font-weight: 400; color: var(--muted); }
    .tool-facts dd { margin: 0; font-size: 15px; font-weight: 500; overflow-wrap: anywhere; }
    .tool-facts dd .sub { display: block; margin-top: 2px; font-size: 12px; font-weight: 400; color: var(--muted); }
    .tool-facts dd svg { display: block; max-width: 100%; height: auto; }
    .sec-title { display: flex; align-items: center; justify-content: space-between; margin: 0; padding: 20px 12px 8px; }
    .sec-title h2 { margin: 0; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
    .sec-title a { font-size: 13px; color: var(--accent); }
    .sec-empty { margin: 0 12px 18px; padding: 18px; border: 1px dashed var(--border); border-radius: 8px; text-align: center; color: var(--muted); }
    @media (max-width: 800px) { .tool-facts { grid-template-columns: 1fr 1fr; } }
</style>
@endonce
