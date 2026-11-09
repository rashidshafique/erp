@extends('layouts.app')
@section('title', 'Invoices')
@section('breadcrumb', 'Invoices')
@section('content')
{{-- Alpine data for the date menu's calendar in the invoice list (Livewire single-file views keep their own scripts). --}}
<script>
    window.invoiceDates = function (perDay, today, from) {
        const MN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const pad = n => String(n).padStart(2, '0');
        const words = iso => { const [y, m, d] = iso.split('-'); return Number(d) + ' ' + MN[Number(m) - 1]; };
        const start = (from || today).split('-').map(Number);
        return {
            open: false, cal: false, mode: 'range', pf: null, pt: null,
            // the left month shown; the right one is the month after
            vy: start[1] === 1 ? start[0] - 1 : start[0], vm: start[1] === 1 ? 11 : start[1] - 2,
            close() { this.open = false; this.cal = false; },
            custom(k) { const w = this.$wire, same = w.period === k && w.from; this.mode = k; this.cal = true; this.pf = same ? w.from : null; this.pt = same ? (w.to || w.from) : null; },
            shift(n) { const d = new Date(this.vy, this.vm + n, 1); this.vy = d.getFullYear(); this.vm = d.getMonth(); },
            months() {
                return [0, 1].map(o => {
                    const f = new Date(this.vy, this.vm + o, 1), y = f.getFullYear(), m = f.getMonth();
                    const lead = (f.getDay() + 6) % 7, n = new Date(y, m + 1, 0).getDate(), days = [];
                    for (let i = 0; i < lead; i++) days.push({ key: y + '-' + m + '-b' + i, iso: null });
                    for (let d = 1; d <= n; d++) { const iso = y + '-' + pad(m + 1) + '-' + pad(d); days.push({ key: iso, iso, n: d }); }
                    return { key: y + '-' + m, name: MN[m] + ' ' + y, days };
                });
            },
            pick(t) {
                if (this.mode === 'day') { this.pf = t; this.pt = t; return; }
                if (! this.pf || this.pt) { this.pf = t; this.pt = null; return; }
                if (t < this.pf) { this.pt = this.pf; this.pf = t; return; }
                this.pt = t;
            },
            cls(t) {
                const end = t === this.pf || t === this.pt, mid = this.pf && this.pt && t > this.pf && t < this.pt;
                return { end, mid, today: t === today, future: t > today, has: !! perDay[t] };
            },
            ready() { return this.mode === 'day' ? !! this.pf : !! (this.pf && this.pt); },
            count() { const a = this.pf, b = this.mode === 'day' ? this.pf : this.pt; return Object.keys(perDay).filter(d => d >= a && d <= b).reduce((s, d) => s + perDay[d], 0); },
            hint() {
                if (this.mode === 'day') return this.pf ? 'Invoices dated ' + words(this.pf) + ' · ' + this.count() : 'Pick a day';
                if (! this.pf) return 'Pick a start date';
                if (! this.pt) return 'From ' + words(this.pf) + ' · pick an end date';
                const n = this.count();
                return words(this.pf) + ' – ' + words(this.pt) + ' · ' + n + (n === 1 ? ' invoice' : ' invoices');
            },
            apply() { if (! this.ready()) return; this.$wire.setRange(this.pf, this.mode === 'day' ? this.pf : this.pt); this.close(); },
        };
    };
</script>
<livewire:invoice-list />
@endsection
