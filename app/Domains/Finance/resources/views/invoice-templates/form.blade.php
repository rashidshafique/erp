@extends('layouts.app')
@section('title', $template->exists ? 'Edit '.$template->name : 'New invoice template')
@section('content')
@php
    $blocks = \App\Domains\Finance\Services\InvoiceLayouts::BLOCKS;
    $state = ['name' => old('name', $template->name), 'applies_to' => old('applies_to', $template->applies_to ?: 'BOTH'), 'layout' => old('layout') ? json_decode(old('layout'), true) : $template->layout];
    // every column is listed so an unused one can be switched on; the saved ones keep their order at the top
    $saved = collect($state['layout']['body']['columns'] ?? [])->keyBy('key');
    $state['layout']['body']['columns'] = $saved->values()->concat(collect(\App\Domains\Finance\Services\InvoiceLayouts::COLUMNS)->reject(fn ($l, $k) => $saved->has($k))->map(fn ($l, $k) => ['key' => $k, 'label' => $l, 'show' => false, 'width' => 0])->values())->all();
@endphp
<div class="page-heading"><div><h1>{{ $template->exists ? 'Edit template' : 'New invoice template' }}</h1><p>Arrange the header, the lines and the footer. The preview on the right is drawn from what you have typed, saved or not.</p></div>
<div class="actions"><a class="btn" href="{{ route('invoice-templates.index') }}">Back</a></div></div>
@error('layout')<div class="alert alert-error">{{ $message }}</div>@enderror
<style>
.tb { display: grid; grid-template-columns: minmax(380px, 1fr) minmax(320px, 1fr); gap: 16px; align-items: start; } @media (max-width: 1100px) { .tb { grid-template-columns: 1fr; } }
.tb-row { display: flex; gap: 6px; flex-wrap: wrap; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 8px; margin-bottom: 8px; background: var(--surface); }
.tb-block { border: 1px solid var(--border); border-radius: 6px; padding: 6px; display: grid; gap: 4px; min-width: 150px; flex: 1; } .tb-block input, .tb-block select { width: 100%; }
.tb-preview { position: sticky; top: 8px; } .tb-preview iframe { width: 100%; height: 78vh; border: 1px solid var(--border); border-radius: 8px; background: #fff; }
.tb-mini { display: flex; gap: 4px; }
</style>
<form method="POST" id="tb-form" action="{{ $template->exists ? route('invoice-templates.update', $template) : route('invoice-templates.store') }}" x-data="templateBuilder(@js($state), @js($blocks))" @submit="$refs.layout.value = JSON.stringify(layout)">
@csrf @if ($template->exists) @method('PUT') @endif
<input type="hidden" name="layout" x-ref="layout">
<div class="tb"><div>
<div class="panel"><div class="panel-body form-grid">
    <label>Name<input name="name" x-model="name" maxlength="120" required></label>
    <label>Used for<select name="applies_to" x-model="applies_to">@foreach (\App\Domains\Finance\Models\InvoiceTemplate::APPLIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
    <label>Paper<select x-model="layout.page.size">@foreach (\App\Domains\Finance\Services\InvoiceLayouts::SIZES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
    <label>Orientation<select x-model="layout.page.orientation"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label>
    <label>Margin (mm)<input type="number" min="5" max="40" x-model.number="layout.page.margin_mm"></label>
    <label>Text size (pt)<input type="number" min="7" max="14" x-model.number="layout.page.font_pt"></label>
</div></div>

@foreach (['header' => ['Header', 'Printed at the top of every page.'], 'intro' => ['Above the lines', 'First page only: title, customer, dates.'], 'footer' => ['Footer', 'Printed at the bottom of every page.']] as $part => [$title, $hint])
<div class="panel"><div class="panel-header"><h2>{{ $title }}</h2><span class="muted small">{{ $hint }}</span></div><div class="panel-body">
    <template x-for="(row, r) in layout.{{ $part }}" :key="r"><div class="tb-row">
        <template x-for="(b, c) in row" :key="c"><div class="tb-block">
            <select x-model="b.kind"><template x-for="(label, key) in kinds" :key="key"><option :value="key" x-text="label" :selected="b.kind === key"></option></template></select>
            <select x-model="b.align"><option value="left">Left</option><option value="center">Centre</option><option value="right">Right</option></select>
            <input x-show="['text','company','title'].includes(b.kind)" x-model="b.text" maxlength="500" :placeholder="b.kind === 'text' ? 'Text, with {invoice_number} {date} {customer} {total} {company}' : (b.kind === 'title' ? 'Title (default: Invoice)' : 'Address lines')">
            <input type="number" min="2" max="80" x-show="b.kind === 'spacer'" x-model.number="b.size" title="Height in pixels">
            <div class="tb-mini"><button type="button" class="btn btn-sm" @click="moveBlock(layout.{{ $part }}, r, c, -1)" :disabled="c === 0" aria-label="Move left">←</button><button type="button" class="btn btn-sm" @click="moveBlock(layout.{{ $part }}, r, c, 1)" :disabled="c === row.length - 1" aria-label="Move right">→</button><button type="button" class="btn btn-sm" @click="removeBlock(layout.{{ $part }}, r, c)" aria-label="Remove block">✕</button></div>
        </div></template>
        <div class="tb-mini"><button type="button" class="btn btn-sm" @click="row.length < 4 && row.push(blank())" :disabled="row.length >= 4">+ Block</button><button type="button" class="btn btn-sm" @click="moveRow(layout.{{ $part }}, r, -1)" :disabled="r === 0" aria-label="Row up">↑</button><button type="button" class="btn btn-sm" @click="moveRow(layout.{{ $part }}, r, 1)" :disabled="r === layout.{{ $part }}.length - 1" aria-label="Row down">↓</button></div>
    </div></template>
    <button type="button" class="btn btn-sm" @click="layout.{{ $part }}.length < 8 && layout.{{ $part }}.push([blank()])">+ Row</button>
</div></div>
@endforeach

<div class="panel"><div class="panel-header"><h2>Lines and totals</h2></div><div class="table-wrap"><table><thead><tr><th>Show</th><th>Heading</th><th>Width %</th><th></th></tr></thead><tbody>
    <template x-for="(col, i) in layout.body.columns" :key="col.key"><tr>
        <td><input type="checkbox" x-model="col.show"></td><td><input x-model="col.label" maxlength="40" style="width:100%"></td><td><input type="number" min="0" max="60" x-model.number="col.width" style="width:70px" title="0 = automatic"></td>
        <td class="actions"><button type="button" class="btn btn-sm" @click="moveColumn(i, -1)" :disabled="i === 0" aria-label="Column up">↑</button><button type="button" class="btn btn-sm" @click="moveColumn(i, 1)" :disabled="i === layout.body.columns.length - 1" aria-label="Column down">↓</button></td></tr></template>
</tbody></table></div>
<div class="panel-body"><strong>Totals under the lines</strong><div style="display:flex;gap:14px;flex-wrap:wrap;margin:6px 0">
    @foreach (\App\Domains\Finance\Services\InvoiceLayouts::TOTALS as $k => $l)<label><input type="checkbox" value="{{ $k }}" x-model="layout.body.totals"> {{ $l }}</label>@endforeach</div>
    <label>Terms printed under the totals<textarea rows="3" maxlength="2000" x-model="layout.body.terms" placeholder="e.g. Pay {terms}. Bank details…"></textarea></label></div></div>

<div class="panel"><div class="panel-body"><button class="btn btn-primary" type="submit">Save template</button> <a class="btn" href="{{ route('invoice-templates.index') }}">Cancel</a></div></div>
</div>
<div class="tb-preview"><div class="panel"><div class="panel-body"><label>Preview with<select x-model="invoice" @change="preview()"><option value="">A sample invoice</option>@foreach ($recent as $i)<option value="{{ $i->id }}">{{ $i->invoice_number }} · {{ $i->customer?->name }}</option>@endforeach</select></label></div></div>
<iframe name="tb-preview-frame" title="Preview"></iframe></div></div>
</form>
<form method="POST" id="tb-preview-form" action="{{ route('invoice-templates.preview') }}" target="tb-preview-frame" style="display:none">@csrf<input type="hidden" name="layout"><input type="hidden" name="invoice"></form>
<script>
function templateBuilder(state, kinds) {
    return {
        name: state.name, applies_to: state.applies_to, layout: state.layout, kinds, invoice: '', timer: null,
        blank() { return { kind: 'text', align: 'left', text: '', size: 12 }; },
        moveRow(rows, r, d) { const t = rows.splice(r, 1)[0]; rows.splice(r + d, 0, t); },
        moveBlock(rows, r, c, d) { const row = rows[r]; const t = row.splice(c, 1)[0]; row.splice(c + d, 0, t); },
        removeBlock(rows, r, c) { rows[r].splice(c, 1); if (rows[r].length === 0) rows.splice(r, 1); },
        moveColumn(i, d) { const t = this.layout.body.columns.splice(i, 1)[0]; this.layout.body.columns.splice(i + d, 0, t); },
        preview() {
            const f = document.getElementById('tb-preview-form');
            f.elements.layout.value = JSON.stringify(this.layout); f.elements.invoice.value = this.invoice; f.submit();
        },
        init() { this.$watch('layout', () => { clearTimeout(this.timer); this.timer = setTimeout(() => this.preview(), 400); }, { deep: true }); this.preview(); },
    };
}
</script>
@endsection
