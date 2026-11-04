<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\Heat;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phone screens for quality techs (designs 26a, 26h): sign in, a home for the tests persona and the defects persona,
 * and a newest-first list of heats or lots to link a record to. Recording opens the existing screens for the chosen heat or lot.
 */
class MobileController extends Controller
{
    /** How many rows and how far back the pick lists go; the tech adjusts both on the list. */
    private const SIZES = [10, 25, 50];

    private const WINDOWS = ['1' => 'Today', '3' => '3 days', '7' => 'Week', 'all' => 'All'];

    /** The test types: whether each needs a heat or a lot, and where recording continues. */
    private const TESTS = [
        'thermal' => ['name' => 'Thermal analysis', 'sub' => 'Cup on the stand, needs a heat', 'on' => 'heat'],
        'spectro' => ['name' => 'Spectro', 'sub' => 'Chemistry for a heat', 'on' => 'heat'],
        'inspection' => ['name' => 'Hardness and inspection', 'sub' => 'Readings against spec, on a lot', 'on' => 'lot'],
    ];

    public function login(): View
    {
        return view('m.login');
    }

    /** A fresh form token, so records saved hours ago can still be sent. */
    public function token(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function home(Request $request): View
    {
        $tests = $this->may('mobile.tests');
        $defects = $this->may('mobile.defects');
        abort_unless($tests || $defects, 403, 'Your role has no phone screens.');

        return view('m.home', [
            'tests' => $tests, 'defects' => $defects, 'types' => self::TESTS,
            'user' => $request->user(),
            'recentHeats' => $tests ? $this->heats(5, 'all') : collect(),
            'todayDefects' => $defects ? \App\Models\QualityDefectMark::whereDate('created_at', today())->count() : 0,
            'todayTests' => $tests ? \App\Models\ThermalAnalysisTest::whereDate('created_at', today())->count() : 0,
        ]);
    }

    public function pickTest(Request $request, string $type): View
    {
        abort_unless(isset(self::TESTS[$type]), 404);
        $needsHeat = self::TESTS[$type]['on'] === 'heat';
        // A thermal test is always linked to a heat; other tests can be linked to a heat or a lot.
        $on = $needsHeat ? 'heat' : ($request->query('on') === 'heat' ? 'heat' : 'lot');

        return $this->pick($request, 'm.tests.pick', ['type' => $type], self::TESTS[$type]['name'], $on, ! $needsHeat, fn ($kind, $row) => $this->testTarget($type, $kind, $row));
    }

    public function pickDefect(Request $request): View
    {
        return $this->pick($request, 'm.defects.pick', [], 'Log a defect', 'lot', false, fn ($kind, $row) => route('lots.show', $row));
    }

    private function pick(Request $request, string $route, array $params, string $title, string $on, bool $either, \Closure $target): View
    {
        $size = (int) $request->query('size', 10);
        $size = in_array($size, self::SIZES, true) ? $size : 10;
        $window = array_key_exists((string) $request->query('within'), self::WINDOWS) ? (string) $request->query('within') : 'all';

        $rows = $on === 'heat' ? $this->heats($size, $window) : $this->lots($size, $window);

        return view('m.pick', [
            'title' => $title, 'on' => $on, 'either' => $either, 'route' => $route, 'params' => $params,
            'size' => $size, 'sizes' => self::SIZES, 'within' => $window, 'windows' => self::WINDOWS,
            'rows' => $rows->map(fn ($row) => ['row' => $row, 'url' => $target($on, $row)]),
        ]);
    }

    private function testTarget(string $type, string $kind, $row): string
    {
        return match ($type) {
            'thermal' => route('thermal.create', ['heat_id' => $row->id]),
            'spectro' => route('heats.show', $row),
            default => route('lots.inspections.create', $row),
        };
    }

    /** Heats newest first, by when they started (else were planned or created). */
    private function heats(int $size, string $window)
    {
        $when = 'coalesce(actual_start_at, planned_start_at, created_at)';

        return Heat::with(['ironGrade', 'furnace'])->whereNotIn('status', ['CANCELLED'])
            ->when($window !== 'all', fn ($q) => $q->whereRaw($when.' >= ?', [now()->subDays((int) $window - 1)->startOfDay()]))
            ->orderByRaw($when.' desc')->limit($size)->get();
    }

    private function lots(int $size, string $window)
    {
        return CastingLot::with('product')
            ->when($window !== 'all', fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $window - 1)->startOfDay()))
            ->latest()->limit($size)->get();
    }

    /** Whether the signed-in person holds a permission; everyone does while login is off. */
    private function may(string $permission): bool
    {
        return ! config('foundry.require_login') || (bool) auth()->user()?->hasPermission($permission);
    }
}
