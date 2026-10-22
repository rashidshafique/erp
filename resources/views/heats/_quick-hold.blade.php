{{-- Fields of the "Put on hold" dialog on a heat (partials.quick-dialog): why the heat is stopped and what is wrong. --}}
@if ($part === 'required')
<div class="field field-full"><label for="qd-hold-reason">Problem <span class="required">*</span></label>
    <select id="qd-hold-reason" name="hold_reason" required><option value="SCRAP" @selected($old('hold_reason') === 'SCRAP')>Scrap problem</option><option value="EQUIPMENT" @selected($old('hold_reason') === 'EQUIPMENT')>Equipment problem</option></select></div>
<div class="field field-full"><label for="qd-hold-note">What is wrong <span class="required">*</span></label>
    <input id="qd-hold-note" name="hold_note" maxlength="500" required value="{{ $old('hold_note') }}"></div>
@endif
