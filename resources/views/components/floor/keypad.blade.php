{{--
    design: C2. The numeric keypad that sits in the screen (not a pop-up), with the Save button under it. Keys type into the count
    field last tapped in the same form; Next moves to the next unlocked field and, on the last, presses Save. Save says what it
    saves: "{total}" in its label becomes the sum of the form's counts. A count in danger disables Save; while saving the keys
    are disabled too. 104 x 64 keys with 8 px gaps, so gloved hands learn one layout.
--}}
@props([
    'save' => 'Save',        // e.g. "Save {total} moulds"
    'disabled' => false,
])
<div {{ $attributes->class(['fl-keypad-wrap']) }}
    x-data="{
        form: null, current: null, total: 0, blocked: false, saving: false, last: false,
        fields() { return this.form ? Array.from(this.form.querySelectorAll('[data-fl-count] input:not([readonly])')) : []; },
        target() { const f = this.fields(); return f.includes(this.current) ? this.current : (f[0] || null); },
        refresh() {
            const f = this.fields();
            this.total = f.reduce((sum, i) => sum + (parseInt(i.value, 10) || 0), 0);
            this.blocked = f.some(i => { const box = i.closest('[data-fl-count]'), max = box.dataset.max; return max !== '' && i.value !== '' && +i.value > +max; });
            this.last = f.length > 0 && f.indexOf(this.target()) === f.length - 1;
        },
        put(fn) {
            const i = this.target(); if (! i || this.saving) { return; }
            i.value = fn(i.value).replace(/^0+(?=\d)/, '').slice(0, 6);
            i.dispatchEvent(new Event('input', { bubbles: true })); i.focus();
        },
        next() {
            const f = this.fields(), at = f.indexOf(this.target());
            if (at >= 0 && at < f.length - 1) { this.current = f[at + 1]; this.current.focus(); this.refresh(); return; }
            if (! this.blocked) { this.$refs.save.click(); }
        },
        init() {
            this.form = this.$el.closest('form');
            if (! this.form) { return; }
            this.form.addEventListener('focusin', e => { if (e.target.closest('[data-fl-count]') && ! e.target.readOnly) { this.current = e.target; this.refresh(); } });
            this.form.addEventListener('input', () => this.refresh());
            this.form.addEventListener('submit', () => { this.saving = true; });
            this.refresh();
        },
        label() { return @js($save).replace('{total}', this.total.toLocaleString()); },
    }">
    <div class="fl-keypad" role="group" aria-label="Number keys">
        @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
            <button type="button" class="fl-key" x-on:pointerdown.prevent x-on:click="put(v => v + '{{ $digit }}')" x-bind:disabled="saving || {{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>{{ $digit }}</button>
        @endforeach
        <button type="button" class="fl-key" aria-label="Delete" x-on:pointerdown.prevent x-on:click="put(v => v.slice(0, -1))" x-bind:disabled="saving || {{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>⌫</button>
        <button type="button" class="fl-key" x-on:pointerdown.prevent x-on:click="put(v => v + '0')" x-bind:disabled="saving || {{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>0</button>
        <button type="button" class="fl-key is-next" x-on:pointerdown.prevent x-on:click="next()" x-text="last ? 'Save' : 'Next'" x-bind:disabled="saving || {{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>Next</button>
    </div>
    <button type="submit" x-ref="save" class="fl-btn is-primary fl-save" x-text="label()" x-bind:disabled="blocked || saving || {{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>{{ str_replace('{total}', '', $save) }}</button>
</div>
