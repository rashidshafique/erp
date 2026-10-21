{{-- Fields of the quick "Add a customer" dialog (partials.quick-dialog). Address, tax number and terms are added later on the customer. --}}
@if ($part === 'required')
<div class="field"><label for="qd-cust-code">Code</label><input id="qd-cust-code" name="code" required maxlength="40" value="{{ $old('code') }}"></div>
<div class="field"><label for="qd-cust-name">Name</label><input id="qd-cust-name" name="name" required maxlength="200" value="{{ $old('name') }}"></div>
@else
<div class="field"><label for="qd-cust-contact">Contact</label><input id="qd-cust-contact" name="contact_name" maxlength="120" value="{{ $old('contact_name') }}"></div>
<div class="field"><label for="qd-cust-phone">Phone</label><input id="qd-cust-phone" name="phone" maxlength="40" value="{{ $old('phone') }}"></div>
<div class="field field-full"><label for="qd-cust-email">Email</label><input id="qd-cust-email" name="email" type="email" maxlength="160" value="{{ $old('email') }}"></div>
@endif
