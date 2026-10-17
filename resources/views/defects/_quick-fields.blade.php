{{-- Fields of the quick "Add a defect" dialog (partials.quick-dialog). --}}
@php $categories = \App\Http\Controllers\DefectController::CATEGORIES; $groups = \App\Http\Controllers\DefectController::GROUPS; @endphp
@if ($part === 'required')
<div class="field"><label for="qd-defect-code">Code</label><input id="qd-defect-code" name="code" required maxlength="40" placeholder="e.g. BLOWHOLE" value="{{ $old('code') }}"></div>
<div class="field"><label for="qd-defect-name">Name</label><input id="qd-defect-name" name="name" required maxlength="150" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-defect-cat">Category</label><select id="qd-defect-cat" name="category" required>@foreach ($categories as $c)<option @selected($old('category') === $c)>{{ $c }}</option>@endforeach</select></div>
<div class="field"><label for="qd-defect-sev">Severity</label><select id="qd-defect-sev" name="default_severity" required>@foreach (['MINOR', 'MAJOR', 'CRITICAL'] as $s)<option @selected($old('default_severity', 'MAJOR') === $s)>{{ $s }}</option>@endforeach</select></div>
@else
<div class="field"><label for="qd-defect-icf">ICF group</label><select id="qd-defect-icf" name="icf_group"><option value="">—</option>@foreach ($groups as $k => $l)<option value="{{ $k }}" @selected($old('icf_group') === $k)>{{ $k }} — {{ $l }}</option>@endforeach</select></div>
<div class="field field-full"><label for="qd-defect-guide">Inspection guidance</label><textarea id="qd-defect-guide" name="inspection_guidance" maxlength="2000">{{ $old('inspection_guidance') }}</textarea></div>
@endif
