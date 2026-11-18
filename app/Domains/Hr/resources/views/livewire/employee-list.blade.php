<?php

use App\Domains\Hr\Http\Controllers\EmployeeController;
use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\HrDepartment;
use App\Domains\Hr\Models\HrLocation;
use App\Support\ListDetails;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The employees list: status tabs, search and the department and location filters apply without a page load and stay in the
 * address. With the details panel open a row click shows the person beside the list; with it closed a row click opens them.
 */
new class extends Component
{
    use ListDetails;
    use WithPagination;

    #[Url(except: 'current')]
    public string $tab = 'current';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $location = '';

    public ?string $focused = null;

    public function focus(string $id): void
    {
        $this->focused = $id;
    }

    public function showTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, EmployeeController::TABS) ? $tab : 'current';
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'department', 'location'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'department', 'location');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $tab = array_key_exists($this->tab, EmployeeController::TABS) ? $this->tab : 'current';
        $rows = EmployeeController::filtered($tab, ['search' => $this->search, 'department' => $this->department, 'location' => $this->location])->paginate(20);
        $focus = $this->details ? ($rows->getCollection()->firstWhere('id', $this->focused) ?? $rows->getCollection()->first()) : null;

        return [
            'tab' => $tab,
            'rows' => $rows,
            'focus' => $focus,
            'counts' => collect(EmployeeController::TABS)->map(fn ($l, $k) => EmployeeController::filtered($k)->count())->all(),
            'departments' => HrDepartment::where('active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => HrLocation::where('active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
};
?>

<div>
<div class="el-card ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Employees</h1>
        <div class="ls-actions"><a class="ls-btn" href="{{ route('directory.index') }}">Directory</a><a class="ls-btn primary" href="{{ route('hr.employees.create') }}">New employee</a></div>
    </div>
    <nav class="ls-tabs" aria-label="Employee status">@foreach (EmployeeController::TABS as $k => $l)<a href="#" wire:click.prevent="showTab('{{ $k }}')" @class(['on' => $tab === $k])>{{ $l }} <span class="n">{{ $counts[$k] }}</span></a>@endforeach</nav>
    <div class="ls-tools">
        <div style="display:contents">
            <label class="ls-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, code, CNIC or phone" aria-label="Search name, code, CNIC or phone"></label>
            <select wire:model.live="department" aria-label="Department" class="ls-select"><option value="">Department: all</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
            <select wire:model.live="location" aria-label="Location" class="ls-select"><option value="">Location: all</option>@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select>
            @if ($search !== '' || $department !== '' || $location !== '')<button type="button" class="ls-tool" wire:click="resetFilters">Clear</button>@endif
        </div>
    </div>
    <div class="el-grid" @style(['grid-template-columns: minmax(0, 1fr)' => ! $details])>
        <div class="el-main">
            @if ($rows->isEmpty())
                <div class="empty-state"><strong>No employees found</strong><p>Everyone on the payroll, past and present, shows here.</p><a class="btn btn-primary" href="{{ route('hr.employees.create') }}">Add an employee</a></div>
            @else
            <div class="el-table"><table>
                <thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th>Location</th><th>Joined</th><th>Status</th>@unless ($details)<th class="ls-dth">@include('partials.list-details-toggle')</th>@endunless</tr></thead>
                <tbody>
                @foreach ($rows as $x)
                    <tr wire:key="emp-{{ $x->id }}-{{ $details ? 'p' : 'f' }}" tabindex="0" @class(['on' => $details && $focus && $focus->id === $x->id])
                        @if ($details) wire:click="focus('{{ $x->id }}')" x-on:focus="$wire.focus('{{ $x->id }}')" x-on:dblclick="window.location = '{{ route('hr.employees.show', $x) }}'" x-on:keydown.enter="window.location = '{{ route('hr.employees.show', $x) }}'"
                        @else x-data x-on:click="if (! $event.target.closest('a,button,summary,details')) window.location = '{{ route('hr.employees.show', $x) }}'" x-on:keydown.enter="window.location = '{{ route('hr.employees.show', $x) }}'" @endif>
                        <td><a href="{{ route('hr.employees.show', $x) }}" style="color:inherit"><code>{{ $x->code }}</code></a></td>
                        <td style="font-weight:500">{{ $x->full_name }}</td>
                        <td>{{ $x->designation?->name ?? '—' }}</td>
                        <td>{{ $x->department?->name ?? '—' }}</td>
                        <td>{{ $x->location?->name ?? '—' }}</td>
                        <td>{{ $x->joined_on->displayDate() }}</td>
                        <td>@include('hr::_chip', ['s' => $x->status])</td>
                        @unless ($details)<td class="act"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $x->code }}">⋯</summary><div class="ls-pop" role="menu"><a role="menuitem" href="{{ route('hr.employees.show', $x) }}">Open employee</a></div></details></td>@endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>
            @endif
        </div>
        @if ($details)
        <aside class="el-side">
            @if ($focus)
                <div>
                    <div class="ls-ph"><div class="el-h"><a href="{{ route('hr.employees.show', $focus) }}" style="color:inherit"><code>{{ $focus->code }}</code></a></div>@include('partials.list-hide-panel')</div>
                    <div style="margin-top:8px;font-weight:500">{{ $focus->full_name }}</div>
                    <div class="el-sub">{{ $focus->designation?->name ?? '—' }}</div>
                    <div style="display:flex;gap:8px;margin:10px 0"><a class="ls-btn primary" href="{{ route('hr.employees.show', $focus) }}">Open employee</a></div>
                    <div class="el-row"><span>Status</span><span>@include('hr::_chip', ['s' => $focus->status])</span></div>
                    <div class="el-row"><span>Department</span><span>{{ $focus->department?->name ?? '—' }}</span></div>
                    <div class="el-row"><span>Location</span><span>{{ $focus->location?->name ?? '—' }}</span></div>
                    <div class="el-row"><span>Type</span><span>{{ config('hr.employment_types')[$focus->employment_type] ?? $focus->employment_type }}</span></div>
                    <div class="el-row"><span>Joined</span><span>{{ $focus->joined_on->displayDate() }}</span></div>
                    @if ($focus->status === 'PROBATION')<div class="el-row"><span>Probation ends</span><span>{{ $focus->probation_ends_on?->displayDate() ?? '—' }}</span></div>
                    @else<div class="el-row"><span>Confirmed</span><span>{{ $focus->confirmed_on?->displayDate() ?? '—' }}</span></div>@endif
                    <div class="el-row"><span>Phone</span><span>{{ $focus->phone ?: '—' }}</span></div>
                    <div class="el-row"><span>Work email</span><span>{{ $focus->work_email ?: '—' }}</span></div>
                </div>
            @else
                <div class="ls-ph"><div class="el-h">Details</div>@include('partials.list-hide-panel')</div>
                <div class="el-note">Nobody to show.</div>
            @endif
        </aside>
        @endif
    </div>
</div>
</div>
