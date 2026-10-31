{{-- Fields of the enquiry's "Add a prospect" dialog (partials.quick-dialog): just enough to quote to. The code is generated; address, tax number and terms are added when they order. --}}
@if ($part === 'required')
<input type="hidden" name="prospect" value="1">
<div class="field field-full"><label for="qd-pros-name">Name</label><input id="qd-pros-name" name="name" required maxlength="200" value="{{ $old('name') }}"></div>
@else
<div class="field"><label for="qd-pros-contact">Contact</label><input id="qd-pros-contact" name="contact_name" maxlength="120" value="{{ $old('contact_name') }}"></div>
<div class="field"><label for="qd-pros-phone">Phone</label><input id="qd-pros-phone" name="phone" maxlength="40" value="{{ $old('phone') }}"></div>
<div class="field field-full"><label for="qd-pros-email">Email</label><input id="qd-pros-email" name="email" type="email" maxlength="160" value="{{ $old('email') }}"></div>
@endif
