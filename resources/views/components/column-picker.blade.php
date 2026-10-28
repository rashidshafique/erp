{{-- A "Columns" button that lists a table's columns with a tick each, so a viewer can hide the ones they do not need.
     $table: a short name for the table; $columns: key => label. Mark each th and td with data-col="key", and the table (or a
     wrapper around it) with data-table="$table". The choice is kept per viewer in the browser, and is applied by a style rule so
     a Livewire re-render of the rows does not undo it. A column left out of $columns is always shown. --}}
@props(['table', 'columns'])
<div {{ $attributes->class(['colpick']) }}
     x-data="{
        open: false,
        table: @js($table),
        hidden: [],
        init() { try { this.hidden = JSON.parse(localStorage.getItem('cols:' + this.table) || '[]'); } catch (e) { this.hidden = []; } },
        toggle(key) {
            this.hidden = this.hidden.includes(key) ? this.hidden.filter(k => k !== key) : [...this.hidden, key];
            try { localStorage.setItem('cols:' + this.table, JSON.stringify(this.hidden)); } catch (e) {}
        },
        get css() { return this.hidden.map(k => '[data-table=' + JSON.stringify(this.table) + '] [data-col=' + JSON.stringify(k) + ']{display:none}').join(''); },
     }"
     x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <style x-text="css"></style>
    <button type="button" class="colpick-btn" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true">Columns<span x-show="hidden.length" x-text="' (' + hidden.length + ' hidden)'" style="display:none"></span></button>
    <div class="colpick-menu" x-show="open" style="display:none" role="group" aria-label="Show or hide columns">
        @foreach ($columns as $key => $label)
            <label><input type="checkbox" value="{{ $key }}" x-bind:checked="! hidden.includes(@js($key))" x-on:change="toggle(@js($key))"> {{ $label }}</label>
        @endforeach
        <button type="button" class="colpick-reset" x-show="hidden.length" x-on:click="hidden = []; try { localStorage.removeItem('cols:' + table); } catch (e) {}" style="display:none">Show all</button>
    </div>
</div>
@once
<style>
    .colpick { position: relative; display: inline-block; }
    .colpick-btn { padding: 7px 14px; border-radius: 6px; background: var(--panel, #fff); border: 1px solid var(--border, #d9dbde); font-size: 13px; color: var(--text, #1c1d1f); cursor: pointer; }
    .colpick-menu { position: absolute; right: 0; top: calc(100% + 4px); z-index: 30; min-width: 190px; max-height: 340px; overflow-y: auto; padding: 8px; border: 1px solid var(--border, #d9dbde); border-radius: 8px; background: var(--panel, #fff); box-shadow: 0 6px 18px rgba(0, 0, 0, .12); }
    .colpick-menu label { display: flex; align-items: center; gap: 8px; padding: 5px 6px; font-size: 13px; cursor: pointer; white-space: nowrap; }
    .colpick-reset { margin-top: 4px; padding: 5px 6px; border: 0; background: none; color: var(--accent); font-size: 13px; cursor: pointer; }
</style>
@endonce
