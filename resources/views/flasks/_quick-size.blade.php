{{-- Fields of the "Add a flask size" dialog (partials.quick-dialog). --}}
@if ($part === 'required')
<div class="field field-full"><label for="qd-fs-name">Name</label><input id="qd-fs-name" name="name" required maxlength="100" placeholder="e.g. 600 × 500" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-fs-l">Inside length (mm)</label><input id="qd-fs-l" type="number" name="inside_length_mm" min="1" step="any" value="{{ $old('inside_length_mm') }}"></div>
<div class="field"><label for="qd-fs-w">Inside width (mm)</label><input id="qd-fs-w" type="number" name="inside_width_mm" min="1" step="any" value="{{ $old('inside_width_mm') }}"></div>
@else
<div class="field"><label for="qd-fs-c">Cope depth (mm)</label><input id="qd-fs-c" type="number" name="cope_depth_mm" min="1" step="any" value="{{ $old('cope_depth_mm') }}"></div>
<div class="field"><label for="qd-fs-d">Drag depth (mm)</label><input id="qd-fs-d" type="number" name="drag_depth_mm" min="1" step="any" value="{{ $old('drag_depth_mm') }}"></div>
<div class="field"><label for="qd-fs-p">Pin centres (mm)</label><input id="qd-fs-p" type="number" name="pin_centres_mm" min="1" step="any" value="{{ $old('pin_centres_mm') }}"></div>
<div class="field field-full"><label for="qd-fs-n">Notes</label><input id="qd-fs-n" name="notes" maxlength="2000" value="{{ $old('notes') }}"></div>
@endif
