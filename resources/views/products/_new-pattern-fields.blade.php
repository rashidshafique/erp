{{-- Fields of the "New pattern" dialog opened from the link-a-pattern dialog (partials.quick-dialog): the name and type only. The
     rest is filled in on the pattern page; the first revision is made when the pattern is linked. --}}
@if ($part === 'required')
<div class="field field-full"><label for="np-name">Name <span class="required">*</span></label><input id="np-name" name="name" required maxlength="255" value="{{ $old('name') }}"></div>
<div class="field field-full"><label for="np-type">Type <span class="required">*</span></label>
    <select id="np-type" name="pattern_type" required>@foreach (\App\Models\Pattern::TYPE_LABELS as $key => $label)<option value="{{ $key }}" @selected($old('pattern_type', 'MATCHPLATE') === $key)>{{ $label }}</option>@endforeach</select></div>
@endif
