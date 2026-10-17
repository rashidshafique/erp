{{-- Tabs across the moulding runs area. $active: 'runs' or 'bulk'. Looks like the tab bar of partials.tabs, but each tab is its own page. --}}
<style>
    .page-tabs { display: flex; gap: 2px; border-bottom: 2px solid var(--border); margin: 6px 0 14px; }
    .page-tabs a { padding: 8px 14px; font-weight: 600; color: var(--muted); border-bottom: 3px solid transparent; margin-bottom: -2px; border-radius: 6px 6px 0 0; text-decoration: none; }
    .page-tabs a:hover { color: var(--text); background: var(--surface); }
    .page-tabs a.on { color: var(--accent); border-bottom-color: var(--accent); background: var(--surface); }
</style>
<nav class="page-tabs" aria-label="Moulding runs">
    <a href="{{ route('moulding-runs.index') }}" class="{{ $active === 'runs' ? 'on' : '' }}" @if ($active === 'runs') aria-current="page" @endif>Runs</a>
    <a href="{{ route('moulding-runs.bulk') }}" class="{{ $active === 'bulk' ? 'on' : '' }}" @if ($active === 'bulk') aria-current="page" @endif>Bulk entry</a>
</nav>
