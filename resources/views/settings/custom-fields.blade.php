@extends('layouts.app')
@section('title', 'Custom fields')
@section('content')
<div class="page-heading"><div><h1>Custom fields</h1><p>Extra descriptive information for your records: drawing references, box sizes, special notes. Measured quality data (chemistry, tests) is not stored here.</p></div></div>
<div class="panel"><div class="panel-header"><h2>Add a field</h2></div><div class="panel-body"><form method="POST" action="{{ route('custom-fields.store') }}" class="form-grid">@csrf
<label>Applies to<select name="entity_type">@foreach($entities as $k => $e)<option value="{{ $k }}" @selected(old('entity_type')===$k)>{{ $e[0] }}</option>@endforeach</select></label>
<label>Label<input name="label" value="{{ old('label') }}" required></label>
<label>Type<select name="data_type">@foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('data_type')===$k)>{{ $l }}</option>@endforeach</select></label>
<label>Choices (for choice lists, one per line)<textarea name="options_text" rows="2">{{ old('options_text') }}</textarea></label>
<label>Unit<input name="unit" value="{{ old('unit') }}" maxlength="20"></label><label>Min (numbers)<input type="number" step="any" name="min_value"></label><label>Max (numbers)<input type="number" step="any" name="max_value"></label>
<label>Help text<input name="help_text" maxlength="255"></label><label>Order<input type="number" name="sort_order" value="100"></label>
<label><input type="checkbox" name="is_required" value="1"> Required</label>
<div><button class="btn btn-primary">Add field</button></div></form></div></div>
@foreach($entities as $key => $e)
@if(isset($defs[$key]))
<div class="panel"><div class="panel-header"><h2>{{ $e[0] }}</h2></div><div class="table-wrap"><table><thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Choices</th><th>Unit</th><th>Req.</th><th>Order</th><th>Active</th><th></th></tr></thead><tbody>
@foreach($defs[$key] as $d)
<form method="POST" action="{{ route('custom-fields.update', $d) }}">@csrf @method('PUT')
<tr><td><input name="label" value="{{ $d->label }}"></td><td class="muted small">{{ $d->field_key }}</td><td>{{ $types[$d->data_type] }}</td>
<td><textarea name="options_text" rows="1">{{ implode("\n", $d->options ?? []) }}</textarea></td><td><input name="unit" value="{{ $d->unit }}" size="6"></td>
<td><input type="checkbox" name="is_required" value="1" @checked($d->is_required)></td><td><input type="number" name="sort_order" value="{{ $d->sort_order }}" style="width:70px"></td>
<td><input type="checkbox" name="is_active" value="1" @checked($d->is_active)></td><td><button class="btn">Save</button></td></tr></form>
@endforeach</tbody></table></div></div>
@endif
@endforeach
@endsection
