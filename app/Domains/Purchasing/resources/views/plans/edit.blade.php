@extends('layouts.app')

@section('title', 'Inspection plan · '.$material->code)
@section('breadcrumb')<a href="{{ route('materials.show', $material) }}">{{ $material->code }}</a> / Inspection plan @endsection

@section('content')
@php $rows = old('items', $material->inspectionItems->map->only(['characteristic', 'method', 'min_value', 'max_value', 'unit'])->all()); $rows = array_pad(array_values($rows), count($rows) + 3, []); @endphp
<div class="page-heading">
    <div><h1>Inspection plan</h1><p>{{ $material->code }} — {{ $material->name }}. What is measured when a lot of this material arrives.</p></div>
    <div class="actions"><a class="btn" href="{{ route('materials.show', $material) }}">Back</a></div>
</div>
<form method="POST" action="{{ route('incoming.plan.update', $material) }}">
    @csrf @method('PUT')
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Characteristic</th><th>Method</th><th>Minimum</th><th>Maximum</th><th>Unit</th></tr></thead>
        <tbody>
        @foreach ($rows as $i => $row)
            <tr>
                <td><input name="items[{{ $i }}][characteristic]" maxlength="120" value="{{ $row['characteristic'] ?? '' }}" aria-label="Characteristic"></td>
                <td><input name="items[{{ $i }}][method]" maxlength="150" value="{{ $row['method'] ?? '' }}" aria-label="Method"></td>
                <td><input type="number" step="any" name="items[{{ $i }}][min_value]" value="{{ isset($row['min_value']) ? (float) $row['min_value'] : '' }}" aria-label="Minimum">@error("items.$i.min_value")<div class="field-error">{{ $message }}</div>@enderror</td>
                <td><input type="number" step="any" name="items[{{ $i }}][max_value]" value="{{ isset($row['max_value']) ? (float) $row['max_value'] : '' }}" aria-label="Maximum">@error("items.$i.max_value")<div class="field-error">{{ $message }}</div>@enderror</td>
                <td><input name="items[{{ $i }}][unit]" maxlength="20" value="{{ $row['unit'] ?? '' }}" aria-label="Unit" style="max-width:90px"></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
    <p class="muted small">Leave a row's characteristic blank to remove it. Save again to get more blank rows. Past inspections keep the limits they were measured against.</p>
    <div class="actions"><button class="btn btn-primary" type="submit">Save plan</button></div>
</form>
@endsection
