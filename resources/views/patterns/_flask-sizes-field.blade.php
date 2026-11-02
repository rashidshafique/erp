{{-- The flask sizes a pattern revision fits, with the one it was designed for; a flaskless arrangement takes a block size
     instead. $revision; $mine says whether old input is this form's. --}}
@php
    $chosen = $revision->flaskSizes;
    $sizes = \App\Models\FlaskSize::ordered()->where('status', 'ACTIVE')->orderBy('is_block')->get()->merge($chosen)->unique('id')->sortBy('is_block')->values();
    $fits = ($mine ?? false) ? (array) old('flask_size_ids', []) : $chosen->pluck('id')->all();
    $primary = ($mine ?? false) ? old('primary_flask_size_id') : $chosen->firstWhere('pivot.is_primary', true)?->id;
@endphp
<div class="field field-full flask-sizes-field">
    <span class="label" style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">Flask sizes and block sizes</span>
    <input type="hidden" name="flask_sizes_sent" value="1">
    @if ($sizes->isEmpty())
        <div class="muted small">No flask sizes yet. <a href="{{ route('flasks.index') }}">Add them on the flask board</a>, then choose the ones this pattern fits.</div>
    @else
        <table class="fs-table">
            <thead><tr><th>Fits</th><th>Designed for</th><th>Size</th></tr></thead>
            <tbody>
                @foreach ($sizes as $size)
                    <tr>
                        <td><input type="checkbox" name="flask_size_ids[]" value="{{ $size->id }}" @checked(in_array($size->id, $fits, true)) aria-label="Fits {{ $size->name }}"></td>
                        <td><input type="radio" name="primary_flask_size_id" value="{{ $size->id }}" @checked($primary === $size->id) aria-label="Designed for {{ $size->name }}"></td>
                        <td>{{ $size->name }} @if ($size->is_block)<span class="pill">Block</span> @endif<span class="muted small">{{ $size->dimensions() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="help">Uses the designed size if the station fits it and flasks are free, else another that fits. Flaskless takes a block size instead.</div>
    @endif
</div>
@once
<style>
    .fs-table { width: auto; font-size: 12.5px; }
    .fs-table th, .fs-table td { padding: 3px 10px 3px 0; text-align: left; }
    .fs-table input { width: auto; min-height: 0; }
</style>
@endonce
