{{-- The quotation's one screen is four tabs (design 30f): Summary, Costing, Revisions and Log. The tab is in the address (?tab=). --}}
<nav class="qt-tabs" aria-label="Quotation">
    @foreach (['summary' => 'Summary', 'costing' => 'Costing', 'revisions' => 'Revisions', 'log' => 'Log'] as $key => $label)
        <a href="{{ route('quotations.show', [$q, 'tab' => $key]) }}" @class(['on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}@if ($key === 'revisions') <span class="qt-n">{{ $q->enquiry->quotations->count() }}</span>@endif</a>
    @endforeach
</nav>
<style>
    .qt-tabs { display: flex; gap: 4px; padding: 0 28px; border-bottom: 1px solid var(--border); background: var(--surface); }
    .qt-tabs a { padding: 10px 14px; font-size: 13.5px; color: var(--muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .qt-tabs a.on { color: var(--text); font-weight: 600; border-bottom-color: var(--accent); }
    .qt-tabs .qt-n { margin-left: 4px; padding: 0 7px; border-radius: 9px; background: #eceef0; font-size: 11.5px; }
    @media (max-width: 1000px) { .qt-tabs { padding-left: 16px; padding-right: 16px; overflow-x: auto; } }
</style>
