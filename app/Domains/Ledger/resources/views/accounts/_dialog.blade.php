{{-- New / edit nominal account and section (design 19i to 19l). Expects $d from ChartDialog::dialog(). --}}
@if ($d['open'])
<div class="ac-overlay" wire:click.self="closeDialog" wire:keydown.escape.window="closeDialog">
<div class="ac-dialog" role="dialog" aria-modal="true" aria-labelledby="ac-dialog-title">
    <div class="ac-dialog-head"><h2 id="ac-dialog-title">{{ $d['title'] }}</h2><button type="button" class="ac-x" wire:click="closeDialog" aria-label="Close">×</button></div>
    <div class="ac-dialog-grid">
        <div class="ac-dialog-main">
            <div class="ac-field"><span class="lab">Category <span class="req">*</span></span>
                @if ($d['isEdit'])<div class="ac-locked"><span>{{ $d['catLabel'] }}</span><small>locked</small></div>
                @else<select wire:model.live="m.cat" aria-label="Category"><option value="">Select a category</option>@foreach ($d['categories'] as $c)<option value="{{ $c->code }}">{{ $c->code }}  {{ $c->name }}</option>@endforeach</select>@endif
            </div>
            @if ($d['isAcc'])
            <div class="ac-field"><span class="lab">Section <span class="req">*</span></span>
                @if ($d['isEdit'])<div class="ac-locked"><span>{{ $d['secLabel'] }}</span><small>locked</small></div>
                @else<select wire:model.live="m.sec" aria-label="Section"><option value="">{{ $d['cat'] ? 'Select a section' : 'Pick a category first' }}</option>@foreach ($d['sections'] as $s)<option value="{{ $s->code }}">{{ $s->code }}  {{ $s->name }}</option>@endforeach</select>@endif
            </div>
            @endif
            <div class="ac-field"><span class="lab">{{ $d['nameLabel'] }} <span class="req">*</span></span><input wire:model.live.debounce.250ms="m.name" placeholder="Name" maxlength="150" aria-label="{{ $d['nameLabel'] }}"></div>
            <div class="ac-field"><span class="lab">Code</span><div class="ac-locked"><code style="color:#1c1d1f">{{ $d['code'] }}</code><small>{{ $d['codeLocked'] ? 'locked' : 'automatic' }}</small></div><span class="hint">{{ $d['codeHint'] }}</span></div>
            @if ($d['isAcc'])
            <div class="ac-field"><span class="lab">Description</span><textarea wire:model.live.debounce.250ms="m.desc" placeholder="Description" rows="2" maxlength="300" style="resize:vertical" aria-label="Description"></textarea></div>
            <div class="ac-box" wire:click="toggleCharge" role="checkbox" aria-checked="{{ ($m['dc'] ?? false) ? 'true' : 'false' }}" tabindex="0" wire:keydown.space.prevent="toggleCharge">
                <span @class(['ac-tick', 'on' => $m['dc'] ?? false])>{{ ($m['dc'] ?? false) ? '✓' : '' }}</span>
                <div style="display:flex;flex-direction:column;gap:2px"><span>Deduction and charge</span><span class="ac-note">Offer this account in the charge and deduction boxes on sales invoices, credit notes and orders, e.g. freight, packing, discount.</span></div>
            </div>
            @else
            <div class="ac-field"><span class="lab">Report line <span class="req">*</span></span>
                @if ($d['lineLocked'])<div class="ac-locked"><span>{{ $m['line'] ?? '' }}</span><small>locked</small></div>
                @else<select wire:model.live="m.line" aria-label="Report line"><option value="">Select a report line</option>@foreach ($d['lines'] as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach</select>@endif
                <span class="hint">{{ $d['lineHint'] }}</span>
            </div>
            @endif
            @if ($d['kind'] === 'accEdit')
            <div class="ac-field" style="gap:6px"><span class="lab">Status</span>
                <div class="ac-seg"><button type="button" @class(['on' => ! ($m['inactive'] ?? false)]) wire:click="setInactive(false)" style="font-size:12px">Active</button><button type="button" @class(['on' => $m['inactive'] ?? false]) wire:click="setInactive(true)" @disabled(! $d['canInactive'] && ! ($m['inactive'] ?? false)) style="font-size:12px">Inactive</button></div>
                <span class="hint">{{ $d['statusNote'] }}</span>
            </div>
            @endif
        </div>
        <div class="ac-dialog-side">
            <div class="ac-sec" style="padding-bottom:6px">{{ $d['listTitle'] }}</div>
            <div class="row head"><span>Name</span><span style="text-align:right">Code</span></div>
            @forelse ($d['list'] as $x)
            <div @class(['row', 'on' => $x['on']])><span style="display:flex;gap:6px;align-items:baseline;min-width:0;flex-wrap:wrap"><span @style(['color:#9a9da3' => $x['off'], 'color:#6b6e73' => $x['placeholder'] ?? false])>{{ $x['name'] }}@isset($x['count']) <span style="color:#6b6e73;font-size:11.5px">({{ $x['count'] }})</span>@endisset</span>@if ($x['tag'])<span class="tag">{{ $x['tag'] }}</span>@endif</span><code>{{ $x['code'] }}</code></div>
            @empty
            <div class="ac-muted" style="font-size:12.5px;padding:8px">{{ $d['listEmpty'] }}</div>
            @endforelse
        </div>
    </div>
    <div class="ac-dialog-foot">
        @if ($d['isEdit'])<button type="button" @class(['ac-btn', 'danger' => $d['canDelete']]) wire:click="deleteFromDialog" @disabled(! $d['canDelete']) @if ($d['canDelete']) wire:confirm="Delete {{ $d['code'] }}?" @endif>Delete</button>@endif
        <span @class(['msg', 'bad' => $d['msgBad']])>{{ $d['msg'] }}</span>
        <button type="button" class="ac-btn" wire:click="closeDialog">Cancel</button>
        <button type="button" class="ac-btn primary" wire:click="saveDialog" @disabled(! $d['canSave'])>Save and close</button>
    </div>
</div>
</div>
@endif
