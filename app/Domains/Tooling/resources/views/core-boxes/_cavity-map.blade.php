{{-- A core box's cavities as numbered cells with the core each makes, or Blocked (design 29e, M1). A visual aid only: the cells
     run in cavity-number order and do not show where a cavity sits in the box. $box; $editable (a cell click changes that
     cavity) with $cores, the cores offered in the dialog. --}}
@php
    $editable = $editable ?? false;
    $cells = $box->cavityCells();
    $tints = \App\Models\CoreBox::MAP_TINTS;
    $tintOf = collect($cells)->filter()->unique('id')->values()->mapWithKeys(fn ($c, $i) => [$c->id => $tints[$i % count($tints)]]);
    $short = fn ($core) => \Illuminate\Support\Str::of($core->name)->replaceMatches('/\s+core$/i', '')->limit(18)->toString();
    $blocked = $box->blockedCavities();
@endphp
@include('tooling::core-boxes._cavity-map-css')
<div class="cb-map {{ $small ?? false ? 'small' : '' }}" @if ($editable) data-cb-map @endif>
    <div class="cb-map-cells">
        @foreach ($cells as $n => $core)
            @php $label = 'Cavity '.$n.': '.($core ? $core->name : 'blocked'); @endphp
            @if ($editable)
                <button type="button" class="cb-cell {{ $core ? '' : 'blocked' }}" @if ($core) style="background:{{ $tintOf[$core->id] }}" @endif
                    data-cavity="{{ $n }}" data-core="{{ $core?->id }}" data-action="{{ route('core-boxes.cavity', [$box, $n]) }}" aria-label="{{ $label }}. Change">
                    <span class="n">{{ $n }}</span><span class="c">{{ $core ? $short($core) : 'Blocked' }}</span></button>
            @else
                <div class="cb-cell {{ $core ? '' : 'blocked' }}" @if ($core) style="background:{{ $tintOf[$core->id] }}" @endif title="{{ $label }}">
                    <span class="n">{{ $n }}</span><span class="c">{{ $core ? $short($core) : 'Blocked' }}</span></div>
            @endif
        @endforeach
    </div>
    <div class="cb-map-key">
        @foreach ($box->coreCounts() as $row)
            @if ($row['core'])<span><i style="background:{{ $tintOf[$row['core']->id] ?? $tints[0] }}" aria-hidden="true"></i><a href="{{ route('cores.show', $row['core']) }}"><span class="mono">{{ $row['core']->code }}</span> {{ $row['core']->name }}</a> × {{ $row['qty'] }}</span>@endif
        @endforeach
        @if ($blocked > 0)<span><i class="cb-cell blocked" style="width:12px;min-height:0;height:12px;padding:0" aria-hidden="true"></i>Blocked × {{ $blocked }}</span>@endif
    </div>
    @if ($editable)
        @error('core')<div class="error">{{ $message }}</div>@enderror
        <p class="muted small" style="margin:0">Click a cavity to change the core it makes or block it.</p>
        <dialog class="qd" data-cb-dialog aria-labelledby="cb-dialog-title">
            <form method="POST" data-cb-form>
                @csrf @method('PUT')
                <div class="qd-head"><h2 id="cb-dialog-title">Cavity <span data-cb-n></span></h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
                <div class="qd-body"><div class="field"><label for="cb-dialog-core">Makes</label>
                    <select id="cb-dialog-core" name="core" data-cb-core>
                        @php $made = collect($box->coreCounts())->pluck('core')->filter(); @endphp
                        <optgroup label="Cores this box makes">@foreach ($made as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach</optgroup>
                        @php $others = $cores->reject(fn ($c) => $made->contains('id', $c->id)); @endphp
                        @if ($others->isNotEmpty())<optgroup label="Other cores">@foreach ($others as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach</optgroup>@endif
                        <option value="">Blocked: makes nothing</option>
                    </select></div></div>
                <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save cavity</button></div>
            </form>
        </dialog>
        <script>
            (function () {
                const map = document.currentScript.closest('[data-cb-map]'), dialog = map.querySelector('[data-cb-dialog]'), form = map.querySelector('[data-cb-form]');
                map.querySelectorAll('[data-cavity]').forEach(cell => cell.addEventListener('click', () => {
                    form.action = cell.dataset.action;
                    map.querySelector('[data-cb-n]').textContent = cell.dataset.cavity;
                    map.querySelector('[data-cb-core]').value = cell.dataset.core || '';
                    dialog.showModal();
                }));
                dialog.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => dialog.close()));
            })();
        </script>
    @endif
</div>
