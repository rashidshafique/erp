<?php

namespace App\Http\Controllers;

use App\Domains\Core\Contracts\PeopleDirectory;
use App\Models\FlaskSize;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\SandSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MouldingResourceController extends Controller
{
    public function index(PeopleDirectory $people): View
    {
        $since = today()->subDays(30);
        $stats = fn (string $col) => MouldingRun::where('run_date', '>=', $since)->whereNotNull($col)->groupBy($col)
            ->selectRaw("$col as k, count(*) runs, coalesce(sum(moulds_made_qty),0) made, coalesce(sum(accepted_mould_qty),0) accepted, coalesce(sum(damaged_mould_qty),0) damaged, coalesce(sum(downtime_minutes),0) downtime")
            ->get()->keyBy('k');

        return view('moulding-teams.index', [
            'teams' => MouldingTeam::withCount('employees')->with(['employees', 'leadEmployee'])->orderBy('code')->get(), 'stations' => MouldingStation::with(['flaskSizes', 'defaultTeam'])->orderBy('code')->get(),
            'employees' => $people->current(), 'hrAvailable' => $people->available(),
            'teamStats' => $stats('moulding_team_id'), 'stationStats' => $stats('moulding_station_id'),
            'stationTypes' => MouldingStation::TYPES, 'sands' => SandSystem::names(),
            'flaskSizes' => FlaskSize::ordered()->where('status', 'ACTIVE')->get(),
        ]);
    }

    /** Adds a team; a quick add from a dropdown asks for JSON and gets back the option to add to it. */
    public function storeTeam(Request $request): RedirectResponse|JsonResponse
    {
        $team = MouldingTeam::create($this->teamData($request));
        $this->syncTeamMembers($request, $team);
        if ($request->wantsJson()) {
            return response()->json(['id' => $team->id, 'label' => "{$team->code} — {$team->lead_name}"], 201);
        }

        return back()->with('success', 'Team added.');
    }

    public function updateTeam(Request $request, MouldingTeam $team): RedirectResponse
    {
        $team->update($this->teamData($request, $team));
        $this->syncTeamMembers($request, $team);

        return back()->with('success', "Team {$team->code} updated.");
    }

    /** Adds a station; a quick add from a dropdown asks for JSON and gets back the option to add to it. */
    public function storeStation(Request $request): RedirectResponse|JsonResponse
    {
        $station = MouldingStation::create($this->stationData($request));
        $this->syncFlaskSizes($request, $station);
        if ($request->wantsJson()) {
            return response()->json(['id' => $station->id, 'label' => "{$station->code} — {$station->name}"], 201);
        }

        return back()->with('success', 'Station added.');
    }

    public function updateStation(Request $request, MouldingStation $station): RedirectResponse
    {
        $station->update($this->stationData($request, $station));
        $this->syncFlaskSizes($request, $station);

        return back()->with('success', "Station {$station->code} updated.");
    }

    private function teamData(Request $request, ?MouldingTeam $team = null): array
    {
        $people = app(PeopleDirectory::class);
        $leadIdWasSubmitted = array_key_exists('lead_employee_id', $request->all());
        $membershipWasSubmitted = $request->boolean('employee_ids_sent');

        $d = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('moulding_teams', 'code')->ignore($team?->id)],
            'name' => ['required', 'string', 'max:100'],
            // lead_name remains for older callers; the dialogs derive it from the selected HR employee.
            'lead_name' => [Rule::requiredIf(! $membershipWasSubmitted && ! $request->filled('lead_employee_id')), 'nullable', 'string', 'max:150'],
            'lead_employee_id' => [Rule::requiredIf($membershipWasSubmitted && $leadIdWasSubmitted), 'nullable', 'uuid', 'exists:employees,id'],
            'lead_phone' => ['nullable', 'string', 'max:40'],
            'member_count' => [Rule::requiredIf(! $membershipWasSubmitted), 'integer', 'min:1', 'max:200'],
            'employee_ids_sent' => ['sometimes', 'in:1'],
            'employee_ids' => ['nullable', 'array', 'max:200'],
            'employee_ids.*' => ['required', 'uuid', 'distinct', 'exists:employees,id'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($membershipWasSubmitted) {
            $ids = array_values(array_unique($d['employee_ids'] ?? []));
            $currentCount = $people->countCurrent($ids);
            if ($currentCount !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'employee_ids' => 'Choose current HR employees only. Employees who have exited cannot be assigned to a moulding team.',
                ]);
            }
            if ($leadIdWasSubmitted) {
                $leadId = $d['lead_employee_id'] ?? null;
            } else {
                // Backwards compatibility for older form posts that submitted memberships before there was a lead selector.
                $legacyLeadName = trim((string) ($d['lead_name'] ?? ''));
                $leadId = $people->idByName($ids, $legacyLeadName) ?? ($ids[0] ?? null);
            }

            if (! $leadId || ! in_array($leadId, $ids, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lead_employee_id' => 'Select the team lead from the employees linked to this team.',
                ]);
            }
            $lead = $people->findCurrent($leadId);
            if (! $lead) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lead_employee_id' => 'The team lead must be a current HR employee.',
                ]);
            }
            $d['lead_employee_id'] = $lead->id;
            $d['lead_name'] = $lead->full_name;
            $d['member_count'] = count($ids);
        } elseif (! empty($d['lead_employee_id'])) {
            $lead = $people->findCurrent($d['lead_employee_id']);
            if (! $lead) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lead_employee_id' => 'The team lead must be a current HR employee.',
                ]);
            }
            if (! $team || ! $team->employees()->whereKey($lead->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lead_employee_id' => 'The team lead must be linked to this team.',
                ]);
            }
            $d['lead_name'] = $lead->full_name;
        }

        unset($d['employee_ids_sent'], $d['employee_ids']);
        $d['code'] = strtoupper($d['code']);

        return $d;
    }

    /** Sync explicitly submitted HR membership; legacy callers without the marker remain compatible. */
    private function syncTeamMembers(Request $request, MouldingTeam $team): void
    {
        if (! $request->boolean('employee_ids_sent')) {
            return;
        }

        $team->employees()->sync($request->input('employee_ids', []));
        $team->update(['member_count' => $team->employees()->count()]);
    }

    private function stationData(Request $request, ?MouldingStation $station = null): array
    {
        $d = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('moulding_stations', 'code')->ignore($station?->id)],
            'name' => ['required', 'string', 'max:100'], 'station_type' => ['required', Rule::in(array_keys(MouldingStation::TYPES))],
            'status' => ['required', 'in:ACTIVE,INACTIVE,MAINTENANCE'], 'notes' => ['nullable', 'string', 'max:1000'],
            'max_patterns' => ['nullable', 'integer', 'min:1', 'max:20'], 'default_moulding_team_id' => ['nullable', 'uuid', 'exists:moulding_teams,id'], 'default_sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
        ]);
        $d['code'] = strtoupper($d['code']);
        $d['max_patterns'] = $d['station_type'] === 'AUTOMATED_LINE' ? 1 : (int) ($d['max_patterns'] ?? ($d['station_type'] === 'MATCHPLATE_MACHINE' ? 1 : 4));
        $d['default_moulding_team_id'] = $d['default_moulding_team_id'] ?? null;
        $d['default_sand_system'] = $d['default_sand_system'] ?? null;
        if ($request->has('flask_sizes_sent')) {
            $d['makes_flaskless'] = $request->boolean('makes_flaskless');
        }

        return $d;
    }

    /** The flask sizes a station handles, when its form sent them. A flaskless station handles none. */
    private function syncFlaskSizes(Request $request, MouldingStation $station): void
    {
        if (! $request->has('flask_sizes_sent')) {
            return;
        }
        $ids = $request->validate(['flask_size_ids' => ['nullable', 'array'], 'flask_size_ids.*' => ['uuid', Rule::exists('flask_sizes', 'id')]])['flask_size_ids'] ?? [];
        $station->flaskSizes()->sync($station->makes_flaskless ? [] : $ids);
    }
}
