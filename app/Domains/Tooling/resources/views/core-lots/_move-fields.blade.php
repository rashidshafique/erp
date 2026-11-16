{{-- Fields of the lot's Move dialog (partials.quick-dialog): where the cores are kept now. --}}
@if ($part === 'required')
    <input type="hidden" name="_method" value="PUT">
    <div class="field field-full"><label for="mv-location">Where they are kept <span class="required">*</span></label><input id="mv-location" name="location" required maxlength="100" list="mv-places" value="{{ $old('location', $lot->location) }}" placeholder="Shelf, rack or bay"><datalist id="mv-places">@foreach ($places as $p)<option value="{{ $p }}">@endforeach</datalist></div>
@endif
