<?php

use App\Domains\Assets\Models\AssetDisposal;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\Disposals;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\PaymentAccounts;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Http\Middleware\EnforceAccess;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Disposing of an asset (design 28h): sell, sell as scrap or write off, with the depreciation to the day, the book value, the gain
 * or loss and the journal shown as the date and price change. A sale raises the invoice to the buyer.
 */
new class extends Component
{
    public FixedAsset $asset;

    public string $kind = 'SELL';

    public string $date = '';

    public string $customerId = '';

    public string $buyerName = '';

    public string $buyerNtn = '';

    public string $price = '';

    public string $weight = '';

    public string $rate = '';

    public string $taxRateId = '';

    public string $money = 'INVOICE';

    public string $method = 'Cash';

    public string $reference = '';

    public string $reason = '';

    public function mount(FixedAsset $asset): void
    {
        $this->asset = $asset;
        $this->date = today()->toDateString();
    }

    public function save(Disposals $disposals): void
    {
        abort_unless(EnforceAccess::allows(Auth::user(), 'assets.in-use'), 403);
        $this->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(AssetDisposal::KINDS))],
            'date' => ['required', 'date'],
            'customerId' => ['nullable', 'uuid', 'exists:customers,id'],
            'buyerName' => ['nullable', 'string', 'max:200'],
            'buyerNtn' => ['nullable', 'string', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'taxRateId' => ['nullable', 'uuid', 'exists:tax_rates,id'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:300', 'required_if:kind,WRITE_OFF'],
        ], ['reason.required_if' => 'Say why it is written off.']);
        try {
            $disposal = $disposals->dispose($this->asset, [
                'kind' => $this->kind, 'date' => $this->date, 'customer_id' => $this->customerId ?: null, 'buyer_name' => $this->buyerName, 'buyer_ntn' => $this->buyerNtn,
                'price' => $this->price, 'weight_kg' => $this->weight, 'rate_per_kg' => $this->rate, 'tax_rate_id' => $this->taxRateId ?: null,
                'received' => $this->money === 'RECEIVED', 'method' => $this->method, 'reference' => $this->reference, 'reason' => $this->reason,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->addError('dispose', $e->getMessage());

            return;
        }
        $done = AssetDisposal::KINDS[$disposal->kind][0] === 'Write off' ? 'written off' : 'sold'.($disposal->invoice ? '; invoice '.$disposal->invoice->invoice_number.' raised' : '');
        session()->flash('success', "{$this->asset->code} {$done}.");
        $this->redirectRoute('assets.show', $this->asset);
    }

    public function with(Disposals $disposals): array
    {
        $date = rescue(fn () => Carbon::parse($this->date)->startOfDay(), today(), false);
        $price = match ($this->kind) {
            'SELL' => round((float) $this->price, 2),
            'SCRAP' => round((float) $this->weight * (float) $this->rate, 2),
            default => 0.0,
        };
        $block = $disposals->block($this->asset, $date);
        $f = $block ? null : $disposals->figures($this->asset, $date, $price);
        $rate = $this->taxRateId ? TaxRate::find($this->taxRateId) : null;
        $tax = $this->kind === 'WRITE_OFF' ? 0.0 : round($price * (float) ($rate?->rate_percent ?? 0) / 100, 2);
        $class = $this->asset->assetClass;
        $name = fn (?string $id) => $id ? LedgerAccount::find($id)?->label() : null;
        $lines = [];
        if ($f) {
            $gain = Disposals::GAIN_ACCOUNT.' Gain or loss on disposal';
            if ($this->kind !== 'WRITE_OFF') {
                $lines[] = [($name(LedgerSettings::account('receivables')->id) ?? 'Receivables').($this->money === 'RECEIVED' ? ', then paid' : ''), $price + $tax, null];
            }
            $lines[] = [$name($class->expense_account_id).', 1–'.$date->format('j M'), $f['charge'], null];
            $lines[] = [$name($class->depreciation_account_id), $f['before'], null];
            $lines[] = [$name($class->cost_account_id), null, $this->asset->cost];
            if ($tax > 0) {
                $lines[] = [$rate?->name ? 'Sales tax output · '.$rate->name : 'Sales tax output', null, $tax];
            }
            $lines[] = [$gain, $f['gain'] < 0 ? -$f['gain'] : null, $f['gain'] > 0 ? $f['gain'] : null];
            $lines = array_values(array_filter($lines, fn ($l) => ($l[1] ?? 0) > 0 || ($l[2] ?? 0) > 0));
        }

        return [
            'block' => $block, 'f' => $f, 'price' => $price, 'tax' => $tax, 'lines' => $lines, 'monthStart' => $date->copy()->startOfMonth(), 'day' => $date,
            'customers' => $this->kind === 'WRITE_OFF' ? collect() : Customer::active()->orderedByName()->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(), 'methods' => PaymentAccounts::METHODS,
        ];
    }
};
?>
@php
    $num = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    $a = $asset;
@endphp
<dialog class="fa-dialog" id="dispose" wire:ignore.self style="width:min(940px, calc(100vw - 32px))" aria-labelledby="dispose-title">
    <form wire:submit="save">
        <div>
            <h2 id="dispose-title" style="margin:0;font-size:16px">Dispose of {{ $a->code }} {{ $a->name }}</h2>
            <p class="fa-sub" style="margin:2px 0 0">{{ $a->assetClass->name }}@if ($a->in_use_on) · in use since {{ $a->in_use_on->displayDate() }}@endif · {{ $a->methodLabel() }}</p>
        </div>
        @error('dispose')<div class="alert alert-error" role="alert">{{ $message }}</div>@enderror
        <div class="fa-dispose">
            <div class="fa-dispose-form">
                <fieldset class="fa-choice">
                    <legend class="fa-sec">What is happening to it</legend>
                    @foreach (['SELL' => 'To a buyer, at a price. Raises a sales invoice.', 'SCRAP' => 'By weight, to a scrap buyer.', 'WRITE_OFF' => 'No money back. Book value becomes a loss.'] as $k => $hint)
                        <label @class(['on' => $kind === $k])><input type="radio" wire:model.live="kind" value="{{ $k }}"><span><b>{{ \App\Domains\Assets\Models\AssetDisposal::KINDS[$k][0] }}</b><span class="fa-sub">{{ $hint }}</span></span></label>
                    @endforeach
                </fieldset>

                @if ($kind !== 'WRITE_OFF')
                    <label>Buyer
                        <select wire:model.live="customerId">
                            <option value="">New buyer…</option>
                            @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}@if ($c->tax_number) · NTN {{ $c->tax_number }}@endif</option>@endforeach
                        </select>
                    </label>
                    @if ($customerId === '')
                        <div class="fa-two">
                            <label>New buyer's name<input type="text" wire:model.blur="buyerName" maxlength="200" placeholder="Al-Noor Engineering Works"></label>
                            <label>NTN<input type="text" wire:model.blur="buyerNtn" maxlength="40"></label>
                        </div>
                        <p class="fa-sub" style="margin:-6px 0 0">Added to customers so the invoice can be raised and paid.</p>
                    @endif
                @endif

                <div class="fa-two">
                    <label>{{ $kind === 'WRITE_OFF' ? 'Date' : 'Date of sale' }}<input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" required></label>
                    @if ($kind === 'SELL')
                        <label>Price, without sales tax<input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="price" inputmode="decimal"></label>
                    @endif
                </div>
                @if ($kind === 'SCRAP')
                    <div class="fa-two">
                        <label>Weight, kg<input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="weight" inputmode="decimal"></label>
                        <label>Rate per kg<input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="rate" inputmode="decimal"></label>
                    </div>
                @endif
                @if ($kind !== 'WRITE_OFF')
                    <label>Sales tax
                        <select wire:model.live="taxRateId"><option value="">None</option>@foreach ($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                        @if ($tax > 0)<span class="fa-sub">= Rs {{ $num($tax) }}, added to the invoice</span>@endif
                    </label>
                    <fieldset class="fa-choice">
                        <legend class="fa-sec">How the money comes in</legend>
                        <label @class(['on' => $money === 'INVOICE'])><input type="radio" wire:model.live="money" value="INVOICE"><span><b>Sales invoice</b><span class="fa-sub">Raises an invoice for Rs {{ $num($price + $tax) }} to the buyer, paid like any invoice.</span></span></label>
                        <label @class(['on' => $money === 'RECEIVED'])><input type="radio" wire:model.live="money" value="RECEIVED"><span><b>Received now</b><span class="fa-sub">Money is in already. Records the receipt against the invoice.</span></span></label>
                    </fieldset>
                    @if ($money === 'RECEIVED')
                        <div class="fa-two">
                            <label>Method<select wire:model="method">@foreach ($methods as $m)<option>{{ $m }}</option>@endforeach</select></label>
                            <label>Reference<input type="text" wire:model="reference" maxlength="100" placeholder="Slip or cheque number"></label>
                        </div>
                    @endif
                @endif
                <label>{{ $kind === 'WRITE_OFF' ? 'Why it is written off' : 'Note' }}<input type="text" wire:model="reason" maxlength="300" @if ($kind === 'WRITE_OFF') required placeholder="Cracked frame, beyond repair" @endif></label>
                @error('reason')<span class="fa-sub" style="color:#b42318">{{ $message }}</span>@enderror

                @if ($a->equipment)
                    <div><div class="fa-sec">On the floor</div><p class="fa-sub" style="margin:0">Equipment {{ $a->equipment->code }} is set disposed on the {{ $kind === 'WRITE_OFF' ? 'date' : 'sale date' }}.</p></div>
                @endif
            </div>

            <div class="fa-dispose-side">
                @if ($block)
                    <div class="alert alert-error" role="alert">{{ $block }}</div>
                @else
                    <div>
                        <div class="fa-sec">Book value {{ $kind === 'WRITE_OFF' ? 'written off' : 'at sale' }}</div>
                        <div class="fa-row"><span>Cost</span><span>{{ $num($a->cost) }}</span></div>
                        <div class="fa-row"><span>Depreciated to {{ $monthStart->copy()->subDay()->format('j M') }}</span><span>−{{ $num($f['before']) }}</span></div>
                        <div class="fa-row"><span>Charge 1–{{ $day->format('j M') }}, posted with the {{ $kind === 'WRITE_OFF' ? 'write-off' : 'sale' }}</span><span>−{{ $num($f['charge']) }}</span></div>
                        <div class="fa-row" style="font-weight:600"><span>Book value on {{ $day->format('j M') }}</span><span style="color:inherit">{{ $num($f['book']) }}</span></div>
                        @if ($kind !== 'WRITE_OFF')<div class="fa-row"><span>Price</span><span>{{ $num($price) }}</span></div>@endif
                        <div class="fa-row" style="font-weight:600;border-bottom:0"><span>{{ $f['gain'] >= 0 ? 'Gain on disposal' : 'Loss on disposal' }}</span><span style="color:{{ $f['gain'] >= 0 ? '#1d7048' : '#b42318' }}">{{ $num(abs($f['gain'])) }}</span></div>
                    </div>
                    <div>
                        <div class="fa-sec">Will post{{ $kind === 'WRITE_OFF' ? '' : ', with the invoice' }}</div>
                        <div class="fa-table"><table>
                            <thead><tr><th>Account</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead>
                            <tbody>
                            @foreach ($lines as [$label, $dr, $cr])
                                <tr><td>{{ $label }}</td><td class="r nw">{{ $dr ? $num($dr) : '' }}</td><td class="r nw">{{ $cr ? $num($cr) : '' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        <p class="fa-sub" style="margin:6px 0 0">Accumulated depreciation is cleared in full ({{ $num($f['cleared']) }}); the line shows it net of the part-month charge. Afterwards the asset shows under Sold or retired in the register.</p>
                    </div>
                @endif
            </div>
        </div>
        <div class="foot">
            <button type="button" class="fa-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="fa-btn primary" @disabled($block) wire:loading.attr="disabled">{{ match ($kind) { 'SELL' => 'Sell and raise invoice', 'SCRAP' => 'Sell as scrap and raise invoice', default => 'Write off' } }}</button>
        </div>
    </form>
</dialog>
