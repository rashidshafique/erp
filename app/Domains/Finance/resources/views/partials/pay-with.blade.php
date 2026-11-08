{{-- Where the money went (design 24d "Into"), the method and the instrument number. Cash as the method picks a cash account and a
     bank method picks a bank, until the account is picked by hand. Needs $accounts, $account, $methods, $into (label), $lab (label class). --}}
@php
    $cashIds = $accounts->where('cash', true)->pluck('id')->values();
    $firstBank = $accounts->firstWhere('cash', false)['id'] ?? null;
    $picked = old('account_id', $account);
@endphp
<div class="pay-with" style="display:contents" x-data="{ account: @js($picked), method: @js(old('method', '')), byHand: @js(filled(old('account_id'))), cash: @js($cashIds), bank: @js($account && ! $cashIds->contains($account) ? $account : $firstBank),
    get kind() { const m = this.method.trim().toLowerCase(); if (m === 'cash') return 'cash'; if (['cheque', 'pay order', 'demand draft'].includes(m)) return 'instrument'; return m === '' ? '' : 'transfer'; },
    get refLabel() { return { cash: 'Receipt no.', instrument: this.method.trim().toLowerCase() === 'cheque' ? 'Cheque no.' : 'Instrument no.', transfer: 'Transaction ID' }[this.kind] ?? 'Instrument no.'; },
    follow() { if (this.byHand || ! this.cash.length) return; if (this.kind === 'cash' && ! this.cash.includes(this.account)) this.account = this.cash[0]; else if (this.kind !== 'cash' && this.kind !== '' && this.cash.includes(this.account) && this.bank) this.account = this.bank; } }">
    @if ($accounts->isNotEmpty())
    <label><span class="{{ $lab }}">{{ $into }}</span>
        <select name="account_id" x-model="account" @change="byHand = true" required aria-label="{{ $into }}">
            @foreach ($accounts as $a)<option value="{{ $a['id'] }}" @selected($picked === $a['id'])>{{ $a['label'] }}</option>@endforeach
        </select></label>
    @endif
    <label><span class="{{ $lab }}">Method · <span x-text="refLabel">Instrument no.</span></span>
        <div style="display:flex;gap:6px">
            <input name="method" list="pay-methods" x-model="method" @change="follow()" @input.debounce.300ms="follow()" maxlength="30" placeholder="Cash, cheque…" aria-label="Method" style="flex:0 0 42%;min-width:0">
            <input name="reference" value="{{ old('reference') }}" maxlength="100" :placeholder="kind === 'cash' ? 'Optional' : refLabel" aria-label="Instrument number" style="min-width:0">
        </div>
        <datalist id="pay-methods">@foreach ($methods as $m)<option value="{{ $m }}"></option>@endforeach</datalist></label>
</div>
