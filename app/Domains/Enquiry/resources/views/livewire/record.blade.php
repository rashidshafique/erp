<?php

use App\Domains\Core\ModuleRegistry;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Development\Support\DevelopmentRules;
use App\Domains\Enquiry\Http\Controllers\EnquiryController;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\EnquiryQuestion;
use App\Domains\Enquiry\Services\EnquiryCloser;
use App\Domains\Enquiry\Services\QuotationService;
use App\Http\Middleware\EnforceAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The enquiry record once it is sent (design 14b), with everything done on it as Livewire actions: the pattern decision,
 * questions to the customer and their answers, the log, creating the quotation and closing the enquiry (14c). The routes
 * these replaced still exist; every action checks the same permission they sat behind.
 */
new class extends Component
{
    #[Locked]
    public string $enquiryId = '';

    public string $route = '';

    public ?string $patternId = null;

    public bool $askOpen = false;

    public string $newQuestion = '';

    public string $askedOn = '';

    /** @var array<string, string> */
    public array $answers = [];

    public string $logKind = 'NOTE';

    public string $logDirection = '';

    public string $logText = '';

    public string $logContact = '';

    public string $logOn = '';

    public string $outcome = 'LOST';

    public ?string $closeReason = null;

    public string $winningPrice = '';

    public string $note = '';

    public bool $followUp = false;

    public string $followUpOn = '';

    public ?string $notice = null;

    public bool $noticeError = false;

    public function mount(Enquiry $enquiry): void
    {
        $this->enquiryId = $enquiry->id;
        $this->route = (string) $enquiry->pattern_route;
        $this->patternId = $enquiry->pattern_id;
        $this->askedOn = today()->toDateString();
        $this->logOn = today()->toDateString();
        $this->followUpOn = today()->addMonths(6)->toDateString();
    }

    private function enquiry(): Enquiry
    {
        return Enquiry::findOrFail($this->enquiryId);
    }

    /** The enquiry, when the user may change it and it is still open. */
    private function editable(): Enquiry
    {
        abort_unless(EnforceAccess::allows(Auth::user(), 'enquiries.store'), 403);
        $e = $this->enquiry();
        abort_unless($e->isOpen(), 422);

        return $e;
    }

    private function say(string $message, bool $error = false): void
    {
        $this->notice = $message;
        $this->noticeError = $error;
    }

    public function savePattern(): void
    {
        $e = $this->editable();
        $this->validate([
            'route' => ['required', Rule::in(array_keys(Enquiry::PATTERN_ROUTES))],
            'patternId' => ['nullable', Rule::exists('patterns', 'id')->whereNull('deleted_at')],
        ], [], ['route' => 'pattern decision', 'patternId' => 'pattern']);
        if ($this->route === 'EXISTING' && blank($this->patternId)) {
            $this->addError('patternId', 'Choose the pattern from the register.');

            return;
        }
        $before = $e->pattern_route;
        $e->update(['pattern_route' => $this->route, 'pattern_id' => $this->route === 'EXISTING' ? $this->patternId : null]);
        $this->say('Pattern decision saved.');
        if ($before !== $e->pattern_route) {
            EnquiryEvent::system($e, 'Tooling and pattern: '.Enquiry::PATTERN_ROUTES[$e->pattern_route][0].($e->pattern ? ' '.$e->pattern->code : '').'.');
            if (class_exists(DevelopmentOpener::class) && app(ModuleRegistry::class)->isEnabled('development') && DevelopmentRules::opensWhenDecided()) {
                $opened = app(DevelopmentOpener::class)->onDecision($e->load('customer'));
                if ($opened->isNotEmpty()) {
                    $this->say('Pattern decision saved. Pattern development '.$opened->pluck('code')->implode(', ').' opened.');
                }
                foreach (app(DevelopmentOpener::class)->keptOpen($e) as $kept) {
                    $this->say("Pattern decision saved. {$kept->code} stays open: {$kept->startedTools()->pluck('label')->implode(', ')} already started. Cancel it there if it should stop.", true);
                }
            }
        }
    }

    public function addQuestion(): void
    {
        $e = $this->editable();
        $this->validate(['newQuestion' => ['required', 'string', 'max:2000'], 'askedOn' => ['nullable', 'date']], [], ['newQuestion' => 'question']);
        $e->questions()->create(['question' => $this->newQuestion, 'asked_on' => $this->askedOn ?: today(), 'asked_by_name' => Auth::user()?->name]);
        $this->reset('newQuestion', 'askOpen');
        $this->say('Question added.');
    }

    public function saveAnswer(string $id): void
    {
        $e = $this->editable();
        $question = EnquiryQuestion::where('enquiry_id', $e->id)->findOrFail($id);
        $this->validate(['answers.'.$id => ['required', 'string', 'max:2000']], [], ['answers.'.$id => 'answer']);
        $question->update(['answer' => $this->answers[$id], 'answered_on' => today()]);
        unset($this->answers[$id]);
        $this->say('Answer recorded.');
    }

    public function addLog(): void
    {
        $e = $this->enquiry();
        abort_unless(EnforceAccess::allows(Auth::user(), 'enquiries.log'), 403);
        $this->validate([
            'logKind' => ['required', Rule::in(['EMAIL', 'CALL', 'MEETING', 'NOTE'])],
            'logDirection' => ['nullable', Rule::in(['IN', 'OUT'])],
            'logText' => ['required', 'string', 'max:3000'],
            'logContact' => ['nullable', 'string', 'max:150'],
            'logOn' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['logText' => 'entry', 'logKind' => 'kind', 'logOn' => 'date']);
        $e->events()->create([
            'kind' => $this->logKind, 'direction' => $this->logKind === 'NOTE' ? null : ($this->logDirection ?: null), 'text' => $this->logText,
            'contact' => $this->logContact ?: null, 'user_name' => Auth::user()?->name, 'happened_on' => $this->logOn ?: today(),
        ]);
        $this->reset('logText', 'logContact', 'logDirection');
        $this->say('Added to the log.');
    }

    public function newQuotation(QuotationService $quotations): void
    {
        $e = $this->editable();
        try {
            $quote = $quotations->create($e);
        } catch (\RuntimeException $ex) {
            $this->say($ex->getMessage(), true);

            return;
        }
        $this->redirectRoute('quotations.show', $quote, navigate: true);
    }

    public function closeEnquiry(EnquiryCloser $closer): void
    {
        $e = $this->editable();
        $d = $this->validate([
            'outcome' => ['required', Rule::in(array_keys(Enquiry::OUTCOMES))],
            'closeReason' => ['nullable', Rule::in(array_keys(Enquiry::CLOSE_REASONS))],
            'winningPrice' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'followUp' => ['boolean'],
            'followUpOn' => ['nullable', 'required_if:followUp,true', 'date', 'after:today'],
        ], ['followUpOn.required_if' => 'Choose the date to follow up.', 'followUpOn.after' => 'The follow-up date must be after today.']);
        $won = $d['outcome'] === 'WON';

        try {
            $result = $closer->close($e, [
                'outcome' => $d['outcome'],
                'close_reason' => $won ? null : $d['closeReason'],
                'winning_price' => $d['outcome'] === 'LOST' ? ($d['winningPrice'] ?: null) : null,
                'note' => $d['note'] ?: null,
                'follow_up' => ! $won && $d['followUp'],
                'follow_up_on' => ! $won && $d['followUp'] ? $d['followUpOn'] : null,
            ]);
        } catch (ValidationException $ex) {
            $this->setErrorBag($ex->validator->errors());

            return;
        }

        if ($result['order'] && Route::has('sales-orders.show')) {
            session()->flash('success', "{$e->code} won. Sales order {$result['order']->order_number} created"
                .($result['developments']->isNotEmpty() ? '. Pattern development '.$result['developments']->pluck('code')->implode(', ').' open.' : '.'));
            $this->redirectRoute('sales-orders.show', $result['order'], navigate: true);

            return;
        }
        session()->flash('success', "{$e->code} closed as ".strtolower(Enquiry::OUTCOMES[$d['outcome']][0]).'.');
        $this->redirectRoute('enquiries.show', $e, navigate: true);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $e = $this->enquiry()->load('customer', 'items.product:id,code,name', 'items.analysis', 'owner:id,name', 'pattern:id,code,name', 'questions', 'events', 'developments', 'quotations.lines', 'quotations.salesOrder:id,order_number', 'items.reviews');

        return EnquiryController::recordData($e);
    }
};
?>

<div>
@php
    $open = $e->isOpen();
    $stage = $e->stage();
    $step = $e->stageStep();
    $tones = ['FEASIBILITY' => 'er-blue', 'QUOTING' => 'er-blue', 'QUOTED' => 'er-ok', 'ON_HOLD' => 'er-bad', 'WON' => 'er-ok', 'LOST' => 'er-bad', 'DECLINED' => 'er-grey', 'CANCELLED' => 'er-grey'];
    $qtones = ['DRAFT' => 'er-grey', 'ISSUED' => 'er-blue', 'ACCEPTED' => 'er-ok', 'REJECTED' => 'er-bad', 'EXPIRED' => 'er-bad', 'SUPERSEDED' => 'er-grey'];
    $etones = ['EMAIL' => 'er-blue', 'CALL' => 'er-blue', 'MEETING' => 'er-blue', 'NOTE' => 'er-grey', 'SYSTEM' => 'er-grey'];
    $days = $e->daysToReply();
    $value = $e->annualValue();
    $openQuestions = $e->questions->filter->isOpen()->count();
    $f = $feasibility;
    $devOpen = $development && $development->status === 'OPEN';
    $money = fn ($v) => $v >= 1000000 ? \App\Support\Formats::withCurrency(rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.').'M') : \App\Support\Formats::money($v, 0);
    $issued = $e->quotations->where('status', 'ISSUED')->sortByDesc('version')->first();
    $shown = $issued ?? $e->quotations->sortByDesc('version')->first();
    $piece = $shown?->lines->first()?->unit_price;
@endphp
@if ($notice)<div class="alert alert-{{ $noticeError ? 'error' : 'success' }}" role="status">{{ $notice }}</div>@endif
<div class="er-card">
    <div class="er-bar">
        <div class="er-crumb"><a href="{{ route('enquiries.index') }}">Enquiries</a><span class="er-muted">/</span><code>{{ $e->code }}</code><span>{{ $e->items->first()?->description ?? $e->title }}</span><span class="er-pill {{ $tones[$stage] ?? '' }}">{{ Enquiry::STAGES[$stage] }}</span></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if ($open)
                <a class="er-btn" href="{{ route('enquiries.edit', $e) }}">Edit details</a>
                <button class="er-btn" type="button" data-quick="closeDialog">Close enquiry</button>
                @if (Route::has('developments.create'))
                    @if ($devOpen)<a class="er-btn primary" href="{{ route('developments.show', $development) }}">Open {{ $development->code }}</a>
                    @elseif ($e->pattern_route !== 'EXISTING')<a class="er-btn primary" href="{{ route('developments.create', ['from' => 'enquiry:'.$e->id]) }}">Start pattern development</a>@endif
                @endif
            @else
                <a class="er-btn" href="{{ route('enquiries.index') }}">All enquiries</a>
            @endif
        </div>
    </div>
    @php $pageErrors = collect($errors->getMessages())->except(['outcome', 'closeReason', 'winningPrice', 'note', 'followUp', 'followUpOn'])->flatten(); @endphp
    @if ($pageErrors->isNotEmpty())<div class="alert alert-error" style="margin:16px 28px 0">@foreach ($pageErrors as $m)<div>{{ $m }}</div>@endforeach</div>@endif
    @if (! $open)<div class="alert" style="margin:16px 28px 0">{{ Enquiry::STAGES[$stage] }} {{ $e->closed_at?->displayDate() }}@if ($e->close_reason): {{ Enquiry::CLOSE_REASONS[$e->close_reason] ?? $e->close_reason }}@endif @if ($e->winning_price) · winning price {{ \App\Support\Formats::money($e->winning_price) }} / pc @endif @if ($e->lost_reason) · {{ $e->lost_reason }}@endif @if ($e->follow_up_on) · follow up on {{ $e->follow_up_on->displayDate() }}@endif</div>@endif
    <div class="er-grid">
        <div class="er-main">
            <div class="er-steps">
                @foreach (Enquiry::STAGE_STEPS as $k => $label)
                    @php $i = $loop->index; @endphp
                    <div @class(['done' => $i <= $step, 'now' => $i === $step])>{{ $k === 'CLOSED' && ! $open ? Enquiry::STAGES[$stage] : $label }}</div>
                @endforeach
            </div>

            <div class="er-two">
                <div class="er-box"><div class="er-h">Tooling and pattern</div>
                    <form wire:submit="savePattern" id="patternForm">
                    <fieldset @disabled(! $open) style="border:0;margin:0;padding:0;min-width:0">
                        <div class="er-opts">
                            @foreach (Enquiry::PATTERN_ROUTES as $k => [$name, $sub])
                                <label class="er-opt @if ($route === $k) on @endif"><input type="radio" wire:model.live="route" value="{{ $k }}"><div><strong>{{ $name }}</strong><span>{{ $k === 'EXISTING' && $e->pattern ? $e->pattern->code.' · '.$e->pattern->name : ($k === 'NEW' && $patternText && $route === 'NEW' ? $patternText.($devOpen ? ' · '.$development->code : '') : $sub) }}</span></div></label>
                            @endforeach
                        </div>
                        @if ($route === 'EXISTING')<div id="patternPick" style="margin-top:8px" wire:ignore><select wire:model="patternId" data-search-select aria-label="Pattern from the register"><option value="">— choose the pattern —</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" data-sub="{{ $p->name }}" @selected($patternId === $p->id)>{{ $p->code }}</option>@endforeach</select></div>@endif
                        @if ($open)<div style="margin-top:8px"><button class="er-btn" type="submit">Save decision</button></div>@endif
                    </fieldset>
                    </form>
                    @if ($patternLife)
                        <div class="alert alert-{{ $patternLife['status'] === 'LIFE_EXCEEDED' ? 'error' : 'warning' }}" style="margin-top:10px">{{ $e->pattern->code }}: {{ strtolower($patternLife['label']) }}{{ $patternLife['percent'] !== null ? ' ('.round($patternLife['percent']).'% of its life used)' : '' }}.
                            @if (Route::has('developments.create'))<a href="{{ route('developments.create', ['from' => 'pattern:'.$e->pattern_id]) }}">Start replacement</a>@endif</div>
                    @endif
                </div>
                <div class="er-box"><div class="er-h">Feasibility</div>
                    <div class="er-kv">
                        <div><div class="k">Review</div><div>@if ($f['first'] && Route::has('feasibility.show'))<a href="{{ route('feasibility.show', $f['first']) }}">Open</a> · @endif{{ $f['signed'] }} of {{ $f['expected'] }} signed</div></div>
                        <div><div class="k">Waiting on</div><div>{{ $f['waiting']->isEmpty() ? 'No one' : $f['waiting']->implode(', ') }}</div></div>
                        <div><div class="k">Risks raised</div><div>{{ $f['risks']->count() }}@if ($f['risks']->isNotEmpty() && $f['risks']->first()->notes) · {{ \Illuminate\Support\Str::limit($f['risks']->first()->notes, 60) }}@endif</div></div>
                        <div><div class="k">Parts decided</div><div>{{ $f['decided'] }} of {{ $e->items->count() }}</div></div>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px"><div class="er-h">Questions to customer · {{ $openQuestions }} open</div>@if ($open)<button type="button" class="er-link" wire:click="$toggle('askOpen')">+ Add question</button>@endif</div>
                @if ($open && $askOpen)<form wire:submit="addQuestion" class="er-add" id="qForm"><input class="grow" wire:model="newQuestion" maxlength="2000" placeholder="e.g. Is machining stock on face B fixed?" aria-label="Question" autofocus><input type="date" wire:model="askedOn" aria-label="Asked on"><button class="er-btn" type="submit">Add</button></form>@endif
                @if ($e->questions->isEmpty())
                    <div class="er-muted" style="font-size:12.5px">No questions yet.</div>
                @else
                <div class="er-table"><table><thead><tr><th>Question</th><th>Asked</th><th>Answer</th></tr></thead><tbody>
                    @foreach ($e->questions as $q)
                        <tr><td>{{ $q->question }}</td><td style="white-space:nowrap">{{ $q->asked_on->displayShortDate() }}</td>
                            <td>@if (! $q->isOpen()){{ $q->answer }} · {{ $q->answered_on?->displayShortDate() }}
                                @elseif ($open)<form wire:submit="saveAnswer('{{ $q->id }}')" class="er-answer" wire:key="answer-{{ $q->id }}"><span class="er-pill er-warn">Open</span><input wire:model="answers.{{ $q->id }}" maxlength="2000" placeholder="Their answer" aria-label="Answer"><button class="er-btn" type="submit">Save</button></form>
                                @else<span class="er-pill er-warn">Open</span>@endif</td></tr>
                    @endforeach
                </tbody></table></div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px"><div class="er-h">Quotes</div>
                    @if ($open)<form wire:submit="newQuotation"><button class="er-link" type="submit">{{ $e->quotations->firstWhere('status', 'DRAFT') ? 'Open the draft' : ($e->quotations->isEmpty() ? '+ Create quotation' : '+ New version') }}</button></form>@endif</div>
                @if ($e->quotations->isEmpty())
                    <div class="er-muted" style="font-size:12.5px">No quotation yet. Assess each part, then create the quotation to price them.</div>
                @else
                <div class="er-table"><table><thead><tr><th>Code</th><th>Version</th><th class="er-r">Piece price</th><th class="er-r">Tooling</th><th>Status</th></tr></thead><tbody>
                    @foreach ($e->quotations as $q)
                        @php $prices = $q->lines->pluck('unit_price')->filter(); $tooling = $q->lines->sum('tooling_cost'); @endphp
                        <tr><td><a href="{{ route('quotations.show', $q) }}"><code>{{ $q->code }}</code></a></td><td>{{ $q->status === 'DRAFT' ? 'Draft ' : '' }}v{{ $q->version }}</td>
                            <td class="er-r">{{ $prices->isEmpty() ? '—' : ($prices->count() > 1 ? 'from ' : '').\App\Support\Formats::money($prices->min()) }}</td>
                            <td class="er-r">{{ $tooling > 0 ? \App\Support\Formats::money($tooling, 0) : '—' }}</td>
                            <td><span class="er-pill {{ $qtones[$q->status] ?? '' }}">{{ ucfirst(strtolower($q->status)) }}</span></td></tr>
                    @endforeach
                </tbody></table></div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column">
                <div class="er-h">Log</div>
                <form wire:submit="addLog" class="er-add" style="margin:8px 0 4px">
                    <select wire:model="logKind" aria-label="Kind of entry">@foreach (['EMAIL', 'CALL', 'MEETING', 'NOTE'] as $k)<option value="{{ $k }}">{{ EnquiryEvent::KINDS[$k] }}</option>@endforeach</select>
                    <select wire:model="logDirection" aria-label="In or out"><option value="">—</option><option value="IN">In</option><option value="OUT">Out</option></select>
                    <input class="grow" wire:model="logText" maxlength="3000" placeholder="What was said or sent" aria-label="Log entry">
                    <input wire:model="logContact" maxlength="150" placeholder="Who, e.g. buyer" aria-label="Contact" style="width:140px">
                    <input type="date" wire:model="logOn" max="{{ today()->toDateString() }}" aria-label="Date">
                    <button class="er-btn" type="submit">Add</button>
                </form>
                <div class="er-log">
                    @forelse ($e->events as $ev)
                        <div><div><span class="er-pill {{ $etones[$ev->kind] ?? '' }}">{{ EnquiryEvent::KINDS[$ev->kind] ?? $ev->kind }}</span></div>
                            <div style="display:flex;flex-direction:column;gap:3px"><div>{{ $ev->text }}</div><div class="who">{{ collect([$ev->direction ? ['IN' => 'In', 'OUT' => 'Out'][$ev->direction] : null, $ev->contact, $ev->user_name])->filter()->implode(' · ') }}</div></div>
                            <div class="when">{{ $ev->happened_on->displayShortDate() }}</div></div>
                    @empty
                        <div class="er-muted" style="display:block;font-size:12.5px">Nothing logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="er-side">
            <div><code class="er-muted">{{ $e->code }}</code><div style="font-size:16px;font-weight:600;margin-top:2px">{{ $e->items->first()?->description ?? $e->title }}</div><div class="er-muted">{{ $e->customer?->name }}@if ($e->customer_reference) · <code>{{ $e->customer_reference }}</code>@endif</div></div>
            <div class="er-kv">
                <div><div class="k">Received</div><div>{{ $e->received_on?->displayShortDate() }}</div></div>
                <div><div class="k">Reply by</div><div>{{ $e->due_on ? $e->due_on->displayShortDate().($open && $days !== null ? ' · '.($days < 0 ? abs($days).' d late' : $days.' d') : '') : '—' }}</div></div>
                <div><div class="k">Owner</div><div>{{ $e->owner?->name ?? '—' }}</div></div>
                <div><div class="k">Annual value</div><div>{{ $value ? $money($value) : '—' }}</div></div>
            </div>
            <div class="er-preview">
                @if ($preview && $preview->isImage())<img src="{{ route('attachments.download', $preview) }}" alt="{{ $preview->original_name }}">
                @elseif ($preview)<iframe src="{{ route('attachments.download', $preview) }}" title="{{ $preview->original_name }}"></iframe>
                @else<code>{{ $files->first()?->original_name ?? 'No drawing yet' }}</code>@endif
            </div>
            <div><div class="er-h">Linked</div><div style="margin-top:8px">
                <div class="er-row"><span>Intake</span><span>@if ($open)<a href="{{ route('enquiries.edit', $e) }}">Edit details</a>@else{{ $e->items->count() }} {{ \Illuminate\Support\Str::plural('part', $e->items->count()) }}@endif</span></div>
                <div class="er-row"><span>Feasibility</span><span>@if ($f['first'] && Route::has('feasibility.show'))<a href="{{ route('feasibility.show', $f['first']) }}">{{ $f['decided'] === $e->items->count() && $e->items->isNotEmpty() ? 'Decided' : 'In progress' }}</a>@else — @endif</span></div>
                <div class="er-row"><span>Quote</span><span>@if ($latestQuote)<a href="{{ route('quotations.show', $latestQuote) }}"><code>{{ $latestQuote->code }}</code></a> {{ strtolower($latestQuote->status) }}@else Not yet @endif</span></div>
                <div class="er-row"><span>Development</span><span>@if ($development && Route::has('developments.show'))@foreach ($e->developments->sortBy('code') as $dv)<span style="display:block"><a href="{{ route('developments.show', $dv) }}"><code>{{ $dv->code }}</code></a> {{ $dv->status === 'OPEN' ? strtolower($dv->stageName()) : strtolower($dv->status) }}</span>@endforeach @else Not started @endif</span></div>
                <div class="er-row"><span>Sales order</span><span>@if ($order && Route::has('sales-orders.show'))<a href="{{ route('sales-orders.show', $order) }}"><code>{{ $order->order_number }}</code></a>@else On win @endif</span></div>
            </div></div>
        </aside>
    </div>
</div>


@if ($open)
<dialog class="qd ec-modal" id="closeDialog" aria-labelledby="closeTitle" wire:ignore.self>
<form wire:submit="closeEnquiry" id="ecForm" novalidate>
    <div class="qd-head"><h2 id="closeTitle">Close <code>{{ $e->code }}</code> · {{ $e->items->first()?->description ?? $e->title }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        @if ($errors->any())<div class="alert alert-error" role="alert">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
        <div class="ec-cards">
            @foreach (Enquiry::OUTCOMES as $k => [$name, $sub])
                <label class="ec-card @if ($outcome === $k) on @endif"><input type="radio" wire:model.live="outcome" value="{{ $k }}"><div><strong>{{ $name }}</strong><span>{{ $sub }}</span></div></label>
            @endforeach
        </div>
        @if ($outcome === 'WON')<div class="ec-won">
            @if ($issued)Accepts <code>{{ $issued->code }}</code> and creates the sales order.@if ($e->pattern_route === 'NEW' && Route::has('developments.create')) A pattern development (new part) is opened for the new pattern.@endif
            @else There is no issued quotation to accept. Issue the quotation first, then close as won.@endif
        </div>@endif
        @if ($outcome !== 'WON')<div class="ec-f"><span>Reason</span>
            <div class="ec-chips">@foreach (Enquiry::CLOSE_REASONS as $k => $l)<label class="ec-chip @if ($closeReason === $k) on @endif"><input type="radio" wire:model.live="closeReason" value="{{ $k }}">{{ $l }}</label>@endforeach</div>
        </div>@endif
        <div class="ec-grid2">
            <div class="ec-f"><span>Our quote</span><div class="ec-ro">@if ($shown){{ $piece !== null ? \App\Support\Formats::money($piece).' / pc · ' : '' }}<code>{{ $shown->code }}</code>@else No quotation yet @endif</div></div>
            @if ($outcome === 'LOST')<label class="ec-f">Winning price, if known<input type="number" step="any" min="0" wire:model="winningPrice" placeholder="per piece"></label>@endif
        </div>
        <label class="ec-f">Note<textarea wire:model="note" rows="3" placeholder="What the buyer said, and what to do next time"></textarea></label>
        @if ($outcome !== 'WON')<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <label style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" wire:model="followUp" style="accent-color:var(--accent)"> Remind me to follow up on</label>
            <input type="date" wire:model="followUpOn" min="{{ today()->addDay()->toDateString() }}" style="width:auto" aria-label="Follow-up date">
        </div>@endif
    </div>
    <div class="qd-foot"><span class="muted small">Draft quote and feasibility are kept for reference.</span><div><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit" id="ecSubmit" wire:loading.attr="disabled">Close as {{ strtolower(Enquiry::OUTCOMES[$outcome][0]) }}</button></div></div>
</form>
</dialog>
@endif
</div>
