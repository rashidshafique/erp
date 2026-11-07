{{--
    design: C2. A count typed on the floor keypad, never the system keyboard (inputmode none). The field carries the state:
    below what records say is a warning that explains what comes next; above the most allowed is danger and stops Save; a
    locked count says why. It is a real input, so plain form posts and wire:model both work.
--}}
@props([
    'name',
    'label',
    'value' => null,
    'records' => null,       // what records say; typing less warns
    'max' => null,           // the most allowed; typing more is danger and blocks Save
    'hint' => null,          // the line under the field when nothing is wrong
    'below' => null,         // warning words, e.g. "Less than records say (96). Next asks for the reason."
    'above' => null,         // danger words, e.g. "More than were counted ready (93). Lower it to save."
    'locked' => null,        // why the count cannot change any more
    'placeholder' => 'Tap to count',
])
@php
    $hint ??= $records !== null ? 'Records say '.$records.'.' : '';
    $below ??= 'Less than records say ('.$records.').';
    $above ??= 'More than the '.$max.' allowed. Lower it to save.';
    $id = $attributes->get('id', 'count-'.str_replace(['[', ']', '.'], '-', $name));
    $tone = function ($v) use ($records, $max): string {
        if ($v === null || $v === '') {
            return '';
        }

        return match (true) {
            $max !== null && (int) $v > $max => 'bad',
            $records !== null && (int) $v < $records => 'warn',
            default => '',
        };
    };
    $start = $tone($value);
    $words = ['' => $hint, 'warn' => '! '.$below, 'bad' => '! '.$above];
@endphp
<label for="{{ $id }}" {{ $attributes->except('id')->class(['fl-count', 'is-locked' => $locked]) }} data-fl-count
    data-records="{{ $records }}" data-max="{{ $max }}" data-tone="{{ $start }}"
    @unless ($locked)
        x-data="{ tone: @js($start), words: @js($words), records: @js($records), max: @js($max),
            check(v) { this.tone = v === '' ? '' : (this.max !== null && +v > this.max ? 'bad' : (this.records !== null && +v < this.records ? 'warn' : '')); } }"
        x-bind:data-tone="tone"
    @endunless>
    <span class="fl-count-label">{{ $label }}</span>
    @if ($locked)
        <input id="{{ $id }}" type="text" value="{{ $value }}" readonly aria-readonly="true" data-no-unsaved>
        <span class="fl-count-hint">{{ $locked }}</span>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="text" inputmode="none" autocomplete="off" pattern="[0-9]*"
            value="{{ $value }}" placeholder="{{ $placeholder }}" x-on:input="check($event.target.value)"
            aria-describedby="{{ $id }}-hint">
        <span class="fl-count-hint" id="{{ $id }}-hint" x-text="words[tone]">{{ $words[$start] }}</span>
    @endif
</label>
