@extends('layouts.app')

@section('title', 'New casting analysis')
@section('breadcrumb')
<a href="{{ route('casting-analyses.index') }}">Casting analysis</a> / New
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h1>New casting analysis</h1>
        <p>Upload the part (the casting itself, not the mould). Takes a few seconds to a minute.</p>
    </div>
</div>


<form method="POST" action="{{ route('casting-analyses.store') }}" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').textContent='Analysing…'">
    @csrf
    @if (! empty($item))
        <input type="hidden" name="enquiry_item_id" value="{{ $item->id }}">
        <div class="alert alert-success">For enquiry <a href="{{ route('enquiries.show', $item->enquiry) }}">{{ $item->enquiry->code }}</a>, part {{ $item->line_no }}: {{ $item->description }}. The result is linked to the part when it finishes.</div>
    @endif
    <div class="panel">
        <div class="panel-header"><h2>Model</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field field-full">
                    <label for="cad">CAD file (.stl, .stp, .step)</label>
                    <input id="cad" type="file" name="cad" accept=".stl,.stp,.step">
                    <div class="help">STL is read directly. STEP needs the converter in <code>tools/step_to_stl.py</code> (pip install cadquery); if it is missing, export STL instead. Maximum {{ round(config('casting.max_upload_kb') / 1024) }} MB.</div>
                    @error('cad')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                @if ($cad->isNotEmpty())
                    <div class="field field-full">
                        <label for="attachment_id">…or use a file already attached to {{ $product->code }}</label>
                        <select id="attachment_id" name="attachment_id">
                            <option value="">— none —</option>
                            @foreach ($cad as $c)
                                <option value="{{ $c->id }}" @selected(old('attachment_id') === $c->id)>{{ $c->original_name }}{{ $c->title ? ' — '.$c->title : '' }}</option>
                            @endforeach
                        </select>
                        @error('attachment_id')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" placeholder="Defaults to the file name">
                </div>
                <div class="field">
                    <label for="product_id">Product (optional)</label>
                    <select id="product_id" name="product_id">
                        <option value="">— not linked —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id', $product?->id) === $p->id)>{{ $p->code }} — {{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">Linking lets the weight be checked against the product's nominal weight, which catches wrong units.</div>
                </div>
                <div class="field">
                    <label for="units">Units in the file</label>
                    <select id="units" name="units">
                        @foreach (['mm' => 'Millimetres', 'cm' => 'Centimetres', 'm' => 'Metres', 'in' => 'Inches'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('units', 'mm') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <div class="help">STL files carry no units. If the weight in the result looks wrong, this is the usual cause.</div>
                </div>
                <div class="field">
                    <label for="quality">Quality</label>
                    <select id="quality" name="quality">
                        @foreach (['DRAFT' => 'Draft (fastest)', 'STANDARD' => 'Standard', 'FINE' => 'Fine (thin walls, slower)'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('quality', 'STANDARD') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Material and requirements</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="family">Iron family</label>
                    <select id="family" name="family">
                        @foreach (['AUTO' => 'Let the analyser choose', 'GREY' => 'Grey iron', 'DUCTILE' => 'Ductile (SG) iron'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('family', 'AUTO') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="iron_grade_id">Iron grade (optional)</label>
                    <select id="iron_grade_id" name="iron_grade_id">
                        <option value="">— none —</option>
                        @foreach ($grades as $g)
                            <option value="{{ $g->id }}" @selected(old('iron_grade_id') === $g->id)>{{ $g->code }} — {{ $g->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">A grade fixes the family and limits the recommended chemistry to its range.</div>
                </div>
                <div class="field">
                    <label for="min_tensile">Minimum tensile strength (MPa)</label>
                    <input id="min_tensile" type="number" step="1" min="0" name="min_tensile" value="{{ old('min_tensile') }}">
                </div>
                <div class="field">
                    <label for="min_elongation">Minimum elongation (%)</label>
                    <input id="min_elongation" type="number" step="0.5" min="0" name="min_elongation" value="{{ old('min_elongation') }}">
                </div>
            </div>
            <details style="margin-top:14px">
                <summary class="small"><strong>Advanced</strong></summary>
                <div class="form-grid" style="margin-top:12px">
                    <div class="field">
                        <label for="feeder">Feeder type</label>
                        <select id="feeder" name="feeder">
                            @foreach (['AUTO' => 'Automatic', 'CONVENTIONAL' => 'Sand riser', 'INSULATED' => 'Insulating sleeve', 'EXOTHERMIC' => 'Exothermic sleeve'] as $k => $l)
                                <option value="{{ $k }}" @selected(old('feeder', 'AUTO') === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="pour_window_min">Minutes from Mg treatment to last mould</label>
                        <input id="pour_window_min" type="number" step="0.5" min="1" max="60" name="pour_window_min" value="{{ old('pour_window_min', 10) }}">
                        <div class="help">Used for Mg fade (0.001 % per minute in a pouring ladle).</div>
                    </div>
                    <div class="field">
                        <label for="b_override">Chvorinov constant B (min/cm²)</label>
                        <input id="b_override" type="number" step="0.05" min="0.3" max="6" name="b_override" value="{{ old('b_override') }}" placeholder="Default for the iron family">
                        <div class="help">Calibrate from your own pours to turn the freezing times into real minutes.</div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Analyse</button>
        <a class="btn" href="{{ route('casting-analyses.index') }}">Cancel</a>
    </div>
</form>
@endsection
