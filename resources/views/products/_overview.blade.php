{{-- Design 10e: identity and technical facts on the left, commercial and performance in the side panel. $product, $facts. --}}
@php
    $f = $facts;
    $revision = $f['revision'];
    $money = fn ($v) => $v === null ? '—' : \App\Support\Formats::money($v);
    $kg = fn ($v) => $v === null ? '—' : \App\Support\Formats::number($v, 2);
    $current = $product->revisions->first(fn ($r) => $r->id === $revision?->id);
@endphp
<div class="pd-grid">
    <div class="pd-main">
        <div class="pd-facts">
            <div><div class="k">Revision</div><div>{{ $revision ? $revision->revision.($revision->effective_from ? ' · released '.$revision->effective_from->format('j M') : '') : '—' }}</div></div>
            <div><div class="k">Status</div><div><span class="pd-pill {{ $product->active ? 'ok' : '' }}">{{ $product->active ? 'Active' : 'Inactive' }}</span></div></div>
            <div><div class="k">PPAP</div><div>—</div></div>
            <div><div class="k">Customer part no.</div><div class="mono">{{ $f['requirement']?->customer_document_number ?: '—' }}</div></div>
        </div>

        <div class="pd-cols">
            <div>
                <div class="pd-h">Customer</div>
                <dl class="pd-list">
                    <dt>Name</dt><dd>{{ $f['customer'] ?? '—' }}</dd>
                    <dt>Tier</dt><dd>—</dd>
                    <dt>CSR</dt><dd>{{ $f['requirement']?->requirement_code ? $f['requirement']->requirement_code.($f['requirement']->revision_code ? ' rev '.$f['requirement']->revision_code : '') : '—' }}</dd>
                    <dt>Packing spec</dt><dd>—</dd>
                    <dt>Approved for</dt><dd>—</dd>
                </dl>
            </div>
            <div>
                <div class="pd-h">Technical</div>
                <dl class="pd-list">
                    <dt>Alloy</dt><dd class="mono">{{ $f['grade']?->code ?? '—' }}</dd>
                    <dt>Cast / poured</dt><dd>{{ $f['castingKg'] !== null ? $kg($f['castingKg']).($f['pouredKg'] ? ' / '.$kg($f['pouredKg']) : '').' kg' : '—' }}{{ $f['castingKg'] && $f['pouredKg'] ? ' · yield '.round(100 * $f['castingKg'] / $f['pouredKg']).'%' : '' }}</dd>
                    <dt>Process</dt><dd>{{ $f['process'] ?: '—' }}</dd>
                    <dt>Cores</dt><dd>{{ $f['cores']->isEmpty() ? '—' : $f['cores']->map(fn ($c) => ($c->quantity_per_casting > 1 ? $c->quantity_per_casting.' × ' : '').$c->coreDefinition->name)->implode(', ') }}</dd>
                    <dt>Tooling</dt><dd class="mono">{{ $f['patternRevision'] ? $f['patternRevision']->pattern->code : '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="table-wrap">
            <table class="pd-table">
                <thead><tr><th>Rev</th><th>Date</th><th>Change</th><th>Impact</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($product->revisions as $rev)
                        <tr>
                            <td>{{ $rev->revision }}</td>
                            <td>{{ ($rev->effective_from ?? $rev->created_at)->format('d M Y') }}</td>
                            <td>{{ $rev->engineering_notes ?: '—' }}</td>
                            <td class="muted">—</td>
                            <td>
                                @if ($rev->id === $revision?->id)<span class="pd-pill info">Current</span>
                                @elseif ($rev->status === 'DRAFT')<span class="pd-pill warn">Draft</span>
                                @else<span class="pd-pill">Superseded</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No revisions have been recorded for this product.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <aside class="pd-side">
        <div class="pd-h">Commercial</div>
        <div class="pd-row"><span>Price{{ $revision ? ', rev '.$revision->revision : '' }}</span><span>{{ $f['price'] !== null ? $money($f['price']).' / pc' : '—' }}</span></div>
        <div class="pd-row"><span>Material cost</span><span>{{ $f['materialCost'] !== null ? $money($f['materialCost']).' / pc' : '—' }}</span></div>
        <div class="pd-row"><span>Annual volume</span><span>{{ $f['volume'] > 0 ? \App\Support\Formats::number($f['volume'], 0).' pcs' : '—' }}</span></div>
        <div class="pd-row"><span>Open order</span><span>{{ $f['openOrder'] ? $f['openOrder']->order_number.' · '.\App\Support\Formats::number((float) $f['openOrder']->ordered_qty, 0).' pcs' : '—' }}</span></div>
        <hr>
        <div class="pd-h">Last 12 months</div>
        <div class="pd-row"><span>Dispatched</span><span>{{ $f['dispatched'] !== null ? \App\Support\Formats::number($f['dispatched'], 0).' pcs' : '—' }}</span></div>
        <div class="pd-row"><span>Internal rejection</span><span>—</span></div>
        <div class="pd-row"><span>Customer PPM</span><span>—</span></div>
        <div class="pd-row"><span>On-time delivery</span><span>—</span></div>
        <hr>
        <p class="pd-note">Material cost comes from the <a href="#bom">BOM</a>.</p>
    </aside>
</div>
