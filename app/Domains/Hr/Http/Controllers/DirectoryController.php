<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\HrDepartment;
use App\Domains\Hr\Models\HrLocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Who is who: name, role, department and how to reach them. No pay, ID or bank details. Open to every signed-in user. */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $people = Employee::current()->with('department', 'designation', 'location', 'manager')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->search).'%';
                $q->where(fn ($w) => $w->where('first_name', 'ilike', $s)->orWhere('last_name', 'ilike', $s)->orWhere('code', 'ilike', $s)
                    ->orWhereHas('designation', fn ($d) => $d->where('name', 'ilike', $s))->orWhereHas('department', fn ($d) => $d->where('name', 'ilike', $s)));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))
            ->when($request->filled('location'), fn ($q) => $q->where('location_id', $request->location))
            ->orderBy('first_name')->get();

        // Reporting tree over everybody currently employed.
        $all = Employee::current()->with('designation', 'department')->orderBy('first_name')->get();
        $ids = $all->pluck('id')->flip();
        $byManager = $all->groupBy(fn ($e) => $e->manager_id && isset($ids[$e->manager_id]) ? $e->manager_id : 'ROOT');

        return view('hr::directory', [
            'people' => $people, 'byManager' => $byManager, 'roots' => $byManager->get('ROOT', collect()),
            'departments' => HrDepartment::where('active', true)->orderBy('name')->get(), 'locations' => HrLocation::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
