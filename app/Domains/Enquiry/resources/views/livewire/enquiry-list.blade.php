<?php

use App\Domains\Enquiry\Http\Controllers\EnquiryController;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Services\QuotationFigures;
use App\Models\Customer;
use App\Models\User;
use App\Support\ListDetails;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The enquiries list (design 14a): every RFQ from receipt to won or lost. The tabs, search and the customer and owner
 * filters apply without a page load; the state stays in the address under the names the page has always used.
 */
new class extends Component
{
    use ListDetails;
    use WithPagination;

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $customer = '';

    #[Url(except: '')]
    public string $owner = '';

    public ?string $focused = null;

    public function focus(string $id): void
    {
        $this->focused = $id;
    }

    public function showTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, EnquiryController::TABS) ? $tab : 'open';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCustomer(): void
    {
        $this->resetPage();
    }

    public function updatedOwner(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'customer', 'owner');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $tab = array_key_exists($this->tab, EnquiryController::TABS) ? $this->tab : 'open';
        $since = today()->subDays(90);
        $closed90 = Enquiry::whereIn('status', ['LOST', 'DECLINED'])->where('closed_at', '>=', $since);
        $figures = app(QuotationFigures::class);
        $quotedIds = $figures->quotedEnquiries($since);
        $won90 = Enquiry::where('status', 'WON')->where('closed_at', '>=', $since)->count();
        $rows = EnquiryController::filtered($tab, ['search' => $this->search, 'customer' => $this->customer, 'owner' => $this->owner])->paginate(20);
        $focus = $this->details ? ($rows->getCollection()->firstWhere('id', $this->focused) ?? $rows->getCollection()->first()) : null;

        return [
            'tab' => $tab,
            'rows' => $rows,
            'focus' => $focus,
            'counts' => collect(EnquiryController::TABS)->map(fn ($l, $k) => EnquiryController::filtered($k)->count())->all(),
            'customers' => Customer::whereIn('id', Enquiry::select('customer_id'))->orderedByName()->get(),
            'owners' => User::whereIn('id', Enquiry::whereNotNull('owner_id')->select('owner_id'))->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'received' => Enquiry::where('received_on', '>=', $since)->count(),
                'quoted' => $quotedIds->count(),
                'won' => $won90,
                'wonShare' => $quotedIds->count() > 0 ? round(100 * $won90 / $quotedIds->count()) : null,
                'daysToQuote' => $figures->daysToQuote($since),
            ],
            'reasons' => (clone $closed90)->whereNotNull('close_reason')->selectRaw('close_reason, status, count(*) as n')->groupBy('close_reason', 'status')->get()
                ->groupBy('close_reason')->map(fn ($g) => ['lost' => (int) $g->where('status', 'LOST')->sum('n'), 'declined' => (int) $g->where('status', 'DECLINED')->sum('n')])
                ->sortByDesc(fn ($r) => $r['lost'] + $r['declined']),
        ];
    }
};
?>

<div>
@php
    $tones = ['DRAFT' => 'el-grey', 'RECEIVED' => 'el-grey', 'FEASIBILITY' => 'el-blue', 'QUOTING' => 'el-blue', 'QUOTED' => 'el-ok', 'ON_HOLD' => 'el-bad', 'WON' => 'el-ok', 'LOST' => 'el-bad', 'DECLINED' => 'el-grey', 'CANCELLED' => 'el-grey'];
    $money = fn ($v) => $v >= 1000000 ? \App\Support\Formats::withCurrency(rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.').'M') : \App\Support\Formats::money($v, 0);
@endphp
<div class="el-card ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Enquiries</h1>
        <div class="ls-actions"><a class="ls-btn primary" href="{{ route('enquiries.create') }}">New enquiry</a></div>
    </div>
    <nav class="ls-tabs" aria-label="Enquiry stage">@foreach (EnquiryController::TABS as $k => $l)<a href="#" wire:click.prevent="showTab('{{ $k }}')" @class(['on' => $tab === $k])>{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>@endforeach</nav>
    <div class="ls-tools">
        <div style="display:contents">
            <label class="ls-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search code, part, customer or RFQ ref" aria-label="Search code, part, customer or RFQ ref"></label>
            <select wire:model.live="customer" aria-label="Customer" class="ls-select"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}" >{{ $c->name }}</option>@endforeach</select>
            <select wire:model.live="owner" aria-label="Owner" class="ls-select"><option value="">Owner: all</option>@foreach ($owners as $u)<option value="{{ $u->id }}" >{{ $u->name }}</option>@endforeach</select>
            @if ($search !== '' || $customer !== '' || $owner !== '')<button type="button" class="ls-tool" wire:click="resetFilters">Clear</button>@endif
        </div>
        <span class="ls-right">
            <x-column-picker table="enquiries" :columns="['customer' => 'Customer', 'stage' => 'Stage', 'pattern' => 'Pattern', 'value' => 'Annual value', 'reply' => 'Reply by', 'owner' => 'Owner']" />
            <a class="ls-tool ls-ico" href="{{ route('enquiries.export', array_filter(['tab' => $tab === 'open' ? null : $tab, 'search' => $search, 'customer' => $customer, 'owner' => $owner])) }}" title="Export" aria-label="Export"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12m0 0-4-4m4 4 4-4M5 20h14"/></svg></a>
        </span>
    </div>
    <div class="el-grid" @style(['grid-template-columns: minmax(0, 1fr)' => ! $details])>
        <div class="el-main">
            @if ($rows->isEmpty())
                <div class="empty-state"><strong>{{ $tab === 'open' && $search === '' && $customer === '' && $owner === '' ? 'No open enquiries' : 'No enquiry matches' }}</strong><p>Record each request as it comes in, whether it is a repeat, a drawing, a sample or a concept.</p><a class="btn btn-primary" href="{{ route('enquiries.create') }}">Start a request</a></div>
            @else
            <div class="el-table"><table data-table="enquiries">
                <thead><tr><th>Code</th><th>Part</th><th data-col="customer">Customer</th><th data-col="stage">Stage</th><th data-col="pattern">Pattern</th><th class="el-r" data-col="value">Annual value</th><th data-col="reply">Reply by</th><th data-col="owner">Owner</th>@unless ($details)<th class="ls-dth">@include('partials.list-details-toggle')</th>@endunless</tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $stage = $r->stage(); $first = $r->items->first(); $value = $r->annualValue(); $days = $r->daysToReply();
                        $issued = $r->quotations->whereNotNull('issued_at')->sortByDesc('issued_at')->first();
                        $pattern = EnquiryController::patternText($r);
                    @endphp
                    <tr wire:key="enq-{{ $r->id }}-{{ $details ? 'p' : 'f' }}" tabindex="0" @class(['on' => $details && $focus && $focus->id === $r->id])
                        @if ($details) wire:click="focus('{{ $r->id }}')" x-on:focus="$wire.focus('{{ $r->id }}')" x-on:dblclick="window.location = '{{ route('enquiries.show', $r) }}'" x-on:keydown.enter="window.location = '{{ route('enquiries.show', $r) }}'"
                        @else x-data x-on:click="if (! $event.target.closest('a,button,summary,details')) window.location = '{{ route('enquiries.show', $r) }}'" x-on:keydown.enter="window.location = '{{ route('enquiries.show', $r) }}'" @endif>
                        <td><a href="{{ route('enquiries.show', $r) }}" style="color:inherit"><code>{{ $r->code }}</code></a></td>
                        <td><div style="font-weight:500">{{ $first?->description ?? $r->title }}</div><div class="el-sub">{{ collect([$first?->material_text, $first?->annual_qty ? \App\Support\Formats::number($first->annual_qty).' / yr' : null, $r->items->count() > 1 ? '+'.($r->items->count() - 1).' more' : null])->filter()->implode(' · ') ?: Enquiry::SOURCES[$r->source_type] }}</div></td>
                        <td data-col="customer">{{ $r->customer?->name }}</td>
                        <td data-col="stage"><span class="el-pill {{ $tones[$stage] }}">{{ Enquiry::STAGES[$stage] }}</span></td>
                        <td data-col="pattern">@if ($pattern){{ $pattern }}@else<span class="el-sub">Not set</span>@endif</td>
                        <td class="el-r" data-col="value">@if ($value){{ $money($value) }}@else<span class="el-sub">—</span>@endif</td>
                        <td data-col="reply">
                            @if ($stage === 'ON_HOLD')Paused
                            @elseif ($stage === 'QUOTED' && $issued)Sent {{ $issued->issued_at->displayShortDate() }}
                            @elseif (! $r->isOpen()){{ $r->closed_at?->displayShortDate() ?? '—' }}
                            @elseif ($days !== null)<span class="el-pill {{ $days <= 1 ? 'el-bad' : ($days <= 3 ? 'el-warn' : '') }}">{{ $r->due_on->displayShortDate() }} · {{ $days < 0 ? abs($days).' d late' : $days.' d' }}</span>
                            @else<span class="el-sub">—</span>@endif
                        </td>
                        <td data-col="owner">{{ $r->owner?->name ?? '—' }}</td>
                        @unless ($details)<td class="act"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $r->code }}">⋯</summary><div class="ls-pop" role="menu"><a role="menuitem" href="{{ route('enquiries.show', $r) }}">Open enquiry</a><a role="menuitem" href="{{ route('enquiries.edit', $r) }}">Edit</a></div></details></td>@endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>
            @endif
            <div class="el-note">On hold follows the customer's credit hold. Won enquiries link to their order and development.</div>
        </div>
        @if ($details)
        <aside class="el-side">
            @if ($focus)
                @php
                    $fFirst = $focus->items->first(); $fValue = $focus->annualValue(); $fStage = $focus->stage();
                    $fIssued = $focus->quotations->whereNotNull('issued_at')->sortByDesc('issued_at')->first();
                @endphp
                <div>
                    <div class="ls-ph"><div class="el-h"><a href="{{ route('enquiries.show', $focus) }}" style="color:inherit"><code>{{ $focus->code }}</code></a></div>@include('partials.list-hide-panel')</div>
                    <div style="margin-top:8px;font-weight:500">{{ $fFirst?->description ?? $focus->title }}</div>
                    <div class="el-sub">{{ $focus->customer?->name }}</div>
                    <div style="display:flex;gap:8px;margin:10px 0"><a class="ls-btn primary" href="{{ route('enquiries.show', $focus) }}">Open enquiry</a>@if ($focus->isOpen())<a class="ls-btn" href="{{ route('enquiries.edit', $focus) }}">Edit</a>@endif</div>
                    <div class="el-row"><span>Stage</span><span><span class="el-pill {{ $tones[$fStage] }}">{{ Enquiry::STAGES[$fStage] }}</span></span></div>
                    <div class="el-row"><span>Material</span><span>{{ $fFirst?->material_text ?: '—' }}</span></div>
                    <div class="el-row"><span>Annual quantity</span><span>{{ $fFirst?->annual_qty ? \App\Support\Formats::number($fFirst->annual_qty) : '—' }}</span></div>
                    <div class="el-row"><span>Annual value</span><span>{{ $fValue ? $money($fValue) : '—' }}</span></div>
                    <div class="el-row"><span>Pattern</span><span>{{ EnquiryController::patternText($focus) ?? 'Not set' }}</span></div>
                    <div class="el-row"><span>Reply by</span><span>{{ $focus->due_on?->displayShortDate() ?? '—' }}</span></div>
                    <div class="el-row"><span>Quotation sent</span><span>{{ $fIssued ? $fIssued->issued_at->displayShortDate() : '—' }}</span></div>
                    <div class="el-row"><span>RFQ ref</span><span>{{ $focus->customer_reference ?: '—' }}</span></div>
                    <div class="el-row"><span>Source</span><span>{{ Enquiry::SOURCES[$focus->source_type] ?? $focus->source_type }}</span></div>
                    <div class="el-row"><span>Owner</span><span>{{ $focus->owner?->name ?? '—' }}</span></div>
                    @if ($focus->items->count() > 1)<div class="el-row"><span>Lines</span><span>{{ $focus->items->count() }}</span></div>@endif
                </div>
            @endif
            <div><div class="ls-ph"><div class="el-h">Last 90 days</div>@unless ($focus)@include('partials.list-hide-panel')@endunless</div><div style="margin-top:8px">
                <div class="el-row"><span>Received</span><span>{{ $stats['received'] }}</span></div>
                <div class="el-row"><span>Quoted</span><span>{{ $stats['quoted'] }}</span></div>
                <div class="el-row"><span>Won</span><span>{{ $stats['won'] }}{{ $stats['wonShare'] !== null ? ' · '.$stats['wonShare'].'% of quoted' : '' }}</span></div>
                <div class="el-row"><span>Avg. days to quote</span><span>{{ $stats['daysToQuote'] ?? '—' }}</span></div>
            </div></div>
            <div><div class="el-h">Lost and declined, why</div><div style="margin-top:8px">
                @forelse ($reasons as $code => $n)
                    <div class="el-row"><span>{{ Enquiry::CLOSE_REASONS[$code] ?? $code }}</span><span>{{ collect([$n['lost'] ? $n['lost'] : null, $n['declined'] ? $n['declined'].' declined' : null])->filter()->implode(' · ') }}</span></div>
                @empty
                    <div class="el-note">Nothing lost or declined in the last 90 days.</div>
                @endforelse
            </div></div>
        </aside>
        @endif
    </div>
</div>
</div>
