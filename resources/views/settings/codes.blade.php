@extends('layouts.app')
@section('title', 'Codes and marking')
@section('content')
<div class="page-heading"><div><h1>Codes and marking</h1><p>How product, pattern and tooling codes are generated, and how tooling is marked for engraving.</p></div></div>
<form method="POST" action="{{ route('settings.codes.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-header"><h2>Item codes</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Style</label><select name="code_style">@foreach($styles as $k => $l)<option value="{{ $k }}" @selected(\App\Services\Codes\CodeGenerator::codeStyle() === $k)>{{ $l }}</option>@endforeach</select>
<div class="help">Letters and numbers: PRD-00012. Numbers only: the type digit then the number (100012). Letters only: PRDAAAAB.</div></div>
<div class="field"><label>Digits in the running number</label><input type="number" name="code_digits" min="3" max="8" value="{{ \App\Services\Codes\CodeGenerator::codeDigits() }}"></div>
</div>
<table style="margin-top:10px"><thead><tr><th>Type</th><th>Prefix (letters)</th><th>Next code</th></tr></thead><tbody>
@foreach($kinds as $k => $meta)<tr><td>{{ $meta[3] }}</td><td><input name="prefix[{{ $k }}]" maxlength="5" value="{{ \App\Services\Codes\CodeGenerator::prefix($k) }}" style="width:90px;text-transform:uppercase"></td><td><code>{{ $previews[$k] }}</code>@if ($k === 'pattern') <span class="muted small">revisions: <code>{{ $previews[$k] }}{{ \App\Models\PatternRevision::codeSuffix() }}1</code></span>@endif</td></tr>@endforeach
</tbody></table>
<p class="help">A code you type yourself is always accepted if it is not already used. Blank means generate. A pattern revision takes its pattern's code, the revision separator below and its number (0 is the first or the as-imported baseline), so it follows the pattern prefix here. Prefixes are used by the letters styles; numbers only uses a type digit instead.</p>
<div class="field" style="margin-top:10px"><label for="revision_suffix">Revision separator</label><input id="revision_suffix" name="revision_suffix" maxlength="4" value="{{ \App\Models\PatternRevision::codeSuffix() }}" style="width:90px"><div class="help">Pattern code + this + revision number, e.g. P-0123-R1. Applies to revisions created from now on; existing codes never change.</div></div>
</div></div>
<div class="panel"><div class="panel-header"><h2>Engraving marks for tooling</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Characters</label><select name="mark_style">@foreach($styles as $k => $l)<option value="{{ $k }}" @selected(\App\Services\Codes\CodeGenerator::markStyle() === $k)>{{ $l }}</option>@endforeach</select>
<div class="help">Choose numbers only if you engrave with a number die set.</div></div>
<div class="field"><label>Length</label><input type="number" name="mark_length" min="3" max="10" value="{{ \App\Services\Codes\CodeGenerator::markLength() }}"><div class="help">Grows by one character automatically if every combination is ever used.</div></div>
<div class="field field-full"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="mark_avoid_lookalikes" value="1" @checked(\App\Services\Codes\CodeGenerator::setting('mark_avoid_lookalikes', '1') === '1')> Leave out look-alike characters (0 and O, 1 and I, Q)</label><div class="help">Applies to letters and to letters-and-numbers. Numbers only always uses 0–9.</div></div>
</div>
<p>Next mark: <code style="font-size:16px">{{ $nextMark }}</code> <span class="muted small">Marks are issued in order and never reused, and no two tools can share one.</span></p>
</div></div>
<p><button class="btn btn-primary">Save</button></p></form>
@endsection
